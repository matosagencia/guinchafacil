<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Conversion/ConversionService.php';
require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Models/Catalog/ProviderCapability.php';

/**
 * §HIBRIDO-COMPLEMENTAR-01 (27/07/2026) — antes desta correção, o caminho
 * HÍBRIDO (mesmo prestador do socorro no local já tem capacidade de reboque
 * aprovada) pulava direto para 'preparacao_veiculo' sem cobrar nada — mesmo
 * bug de fundo do caminho não-híbrido, nunca corrigido. Estes testes cobrem
 * o novo status 'aguardando_pagamento_reboque_hibrido', a revalidação de
 * capacidade do prestador no momento da aprovação do pagamento (pode ter
 * mudado entre a decisão de conversão e o pagamento efetivo), o downgrade
 * auditado para fila comum quando o prestador deixa de ser elegível, e a
 * liberação correta do prestador em caso de cancelamento.
 */
final class ConversionServiceHibridoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach ([
            'pedido_idempotency', 'pagamentos_arquivados', 'pagamentos', 'payout_ledger_entries',
            'pedido_cancelamentos', 'pedidos', 'veiculos', 'usuarios', 'guinchos',
            'provider_capabilities', 'service_types', 'configuracoes',
        ] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.hibrido@example.com', 'hash', '11999999999', '11111111111', 'cliente')");
        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (2, 'Guincho Teste', 'guincho.hibrido@example.com', 'hash', '11988888888', '22222222222', 'guincho')");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'HIB1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_percentual', '0.30')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_maximo', '40.00')");

        // Service type de reboque (TOW_CAR) — usado pela revalidação de
        // capacidade (ProviderCapability::possuiCapacidadeReboqueAprovada
        // exige attendance_mode='TOWING' no service_type vinculado).
        $pdo->exec("INSERT INTO service_types (id, code, name, attendance_mode, active) VALUES
            (1, 'TOW_CAR', 'Reboque Carro', 'TOWING', 1)");

        // Prestador #10: guincho aprovado, disponivel=0 (ocupado atendendo
        // este pedido), COM capacidade de reboque aprovada — o cenário
        // híbrido.
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (10, 2, 1, 0)");
        $pdo->exec("INSERT INTO provider_capabilities
                        (provider_id, service_type_id, enabled, approval_status, created_at, updated_at)
                     VALUES (10, 1, 1, 'APPROVED', datetime('now'), datetime('now'))");
    }

    private function criarPedidoOnSiteHibrido(int $id, int $guinchoId, float $valorPagoOnSite): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos
                        (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, attendance_mode,
                         lat_origem, lng_origem, endereco_origem)
                     VALUES ({$id}, 'conversao_reboque_pendente', {$valorPagoOnSite}, 1, 1, {$guinchoId}, 'ON_SITE', -23.55, -46.63, 'Origem')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES
                     ({$id}, 'mercadopago', 'aprovado', {$valorPagoOnSite})");
    }

    public function testConversaoHibridaCobraComplementarComCreditoEVaiParaStatusDeEspera(): void
    {
        $this->criarPedidoOnSiteHibrido(501, 10, 50.00);

        $result = ConversionService::decidirConversao(501, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertTrue($result->context['hibrido'] ?? false);
        $this->assertTrue($result->context['aguardando_pagamento_complementar'] ?? false);
        $this->assertGreaterThan(0.0, (float)$result->context['custo_reboque']);

        $pdo = getPDO();
        $pedido = $pdo->query('SELECT status, guincho_id, attendance_mode, custo_estimado FROM pedidos WHERE id = 501')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_reboque_hibrido', $pedido['status']);
        $this->assertSame(10, (int)$pedido['guincho_id'], 'Prestador híbrido continua vinculado — não deve ser solto pra fila comum.');
        $this->assertSame('HYBRID', $pedido['attendance_mode']);

        $guincho = $pdo->query('SELECT disponivel FROM guinchos WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int)$guincho['disponivel'], 'Prestador continua ocupado com este pedido — não deve virar disponível ainda.');

        // Crédito aplicado: R$50 pago no local -> min(50*0.30, 40) = 15.00 abatido.
        require_once __DIR__ . '/../../src/Services/TarifaService.php';
        $tarifaBruta = TarifaService::calcularDetalhado((float)$pedido['custo_estimado'] + 0, 'popular', false); // custo_estimado já com crédito; só usamos a distância abaixo
        $distancia = (float)$pdo->query('SELECT distancia_km FROM pedidos WHERE id = 501')->fetchColumn();
        $bruta = (float)TarifaService::calcularDetalhado($distancia, 'popular', false)['valor'];
        $this->assertEqualsWithDelta($bruta - 15.00, (float)$pedido['custo_estimado'], 0.01);
    }

    public function testPagamentoAprovadoComPrestadorAindaValidoVaiParaPreparacaoVeiculo(): void
    {
        $this->criarPedidoOnSiteHibrido(502, 10, 50.00);
        $conversao = ConversionService::decidirConversao(502, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($conversao->ok, (string)$conversao->error);

        $pagamento = PedidoTransitionService::approvePayment(502, 'mp_hibrido_502', '{}');
        $this->assertTrue($pagamento->ok, (string)$pagamento->error);
        $this->assertSame('preparacao_veiculo', $pagamento->context['status_novo']);
        $this->assertFalse($pagamento->context['downgrade_hibrido'] ?? true);

        $pdo = getPDO();
        $pedido = $pdo->query('SELECT status, guincho_id FROM pedidos WHERE id = 502')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('preparacao_veiculo', $pedido['status']);
        $this->assertSame(10, (int)$pedido['guincho_id'], 'Prestador continua o mesmo — sem nova disputa de matching.');

        $guincho = $pdo->query('SELECT disponivel FROM guinchos WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int)$guincho['disponivel'], 'Prestador segue ocupado com o próprio atendimento, não liberado pra outros chamados.');
    }

    public function testPrestadorPerdeCapacidadeAntesDoPagamentoCaiParaFilaComum(): void
    {
        $this->criarPedidoOnSiteHibrido(503, 10, 50.00);
        $conversao = ConversionService::decidirConversao(503, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($conversao->ok, (string)$conversao->error);

        // Prestador perde a capacidade de reboque DEPOIS da decisão de
        // conversão mas ANTES do pagamento do complementar ser aprovado
        // (ex.: suspensão administrativa nesse meio-tempo).
        $pdo = getPDO();
        $pdo->exec("UPDATE provider_capabilities SET approval_status = 'SUSPENDED' WHERE provider_id = 10");

        $pagamento = PedidoTransitionService::approvePayment(503, 'mp_hibrido_503', '{}');
        $this->assertTrue($pagamento->ok, (string)$pagamento->error);
        $this->assertSame('aguardando_guincho', $pagamento->context['status_novo'], 'Sem capacidade válida, o pagamento aprovado deve rebaixar pra fila comum em vez de travar ou seguir com prestador inválido.');
        $this->assertTrue($pagamento->context['downgrade_hibrido'] ?? false);

        $pedido = $pdo->query('SELECT status, guincho_id, attendance_mode FROM pedidos WHERE id = 503')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedido['status']);
        $this->assertNull($pedido['guincho_id'], 'Downgrade precisa desvincular o prestador inválido — outro prestador assume o reboque.');
        $this->assertSame('TOWING', $pedido['attendance_mode'], 'Fila comum de reboque usa o filtro de capacidade TOWING (reboque_aprovado), não o do serviço original.');

        $guincho = $pdo->query('SELECT disponivel FROM guinchos WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$guincho['disponivel'], 'Prestador rebaixado precisa ser liberado pra outros chamados.');
    }

    public function testCancelamentoDuranteEsperaDoComplementarLiberaOPrestador(): void
    {
        $this->criarPedidoOnSiteHibrido(504, 10, 50.00);
        $conversao = ConversionService::decidirConversao(504, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($conversao->ok, (string)$conversao->error);

        $cancelamento = PedidoTransitionService::cancelByAdmin(504, 99, 'Cliente desistiu antes de pagar o complementar.');
        $this->assertTrue($cancelamento->ok, (string)$cancelamento->error);

        $pdo = getPDO();
        $pedido = $pdo->query('SELECT status FROM pedidos WHERE id = 504')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('cancelado', $pedido['status']);

        $guincho = $pdo->query('SELECT disponivel FROM guinchos WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$guincho['disponivel'], 'Cancelamento durante a espera do complementar precisa liberar o prestador reservado.');
    }
}
