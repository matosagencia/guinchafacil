<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Conversion/ConversionService.php';

/**
 * §CREDITO-CONVERSAO-01 (26/07/2026) — credito_conversao_percentual/
 * credito_conversao_maximo existiam em Configuracao (e na tela admin), mas
 * ConversionService::decidirConversao() nunca lia essas chaves: o cliente
 * pagava o socorro no local e depois o reboque complementar integralmente,
 * sem nenhum abatimento.
 *
 * §HIBRIDO-COMPLEMENTAR-01 (27/07/2026) — o cálculo do crédito passou a usar
 * o pagamento ARQUIVADO (Pagamento::arquivarParaCobrancaComplementar) como
 * fonte, não mais uma query solta em `pagamentos` filtrando só por
 * status='aprovado' (ambígua: não distinguia qual fase do pedido estava
 * sendo lida). Isso também mudou o comportamento do caso "sem pagamento
 * aprovado prévio": antes seguia em frente com crédito zero; agora BLOQUEIA
 * a conversão (indica inconsistência real de dados — nenhum socorro no
 * local chega em conversao_reboque_pendente sem ter sido pago).
 */
final class ConversionServiceCreditoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pedido_idempotency', 'pagamentos_arquivados', 'pagamentos', 'pedidos', 'veiculos', 'usuarios', 'guinchos', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.credito@example.com', 'hash', '11999999999', '11111111111', 'cliente')");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'CRD1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
    }

    private function criarPedidoOnSitePago(int $id, float $valorPagoOnSite): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, attendance_mode,
                        lat_origem, lng_origem, endereco_origem)
                     VALUES ({$id}, 'conversao_reboque_pendente', {$valorPagoOnSite}, 1, 1, 'ON_SITE', -23.55, -46.63, 'Origem')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES
                     ({$id}, 'mercadopago', 'aprovado', {$valorPagoOnSite})");
    }

    private function custoBrutoSemCredito(int $pedidoId): float
    {
        $pdo = getPDO();
        $pedido = $pdo->query("SELECT distancia_km FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        require_once __DIR__ . '/../../src/Services/TarifaService.php';
        $tarifa = TarifaService::calcularDetalhado((float)$pedido['distancia_km'], 'popular', false);
        return (float)$tarifa['valor'];
    }

    public function testCreditoAbatidoDoCustoDoReboqueComplementar(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_percentual', '0.30')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_maximo', '40.00')");
        // Socorro no local pago: R$ 50 -> crédito = min(50*0.30, 40) = 15.00.
        $this->criarPedidoOnSitePago(401, 50.00);

        $result = ConversionService::decidirConversao(401, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);

        $this->assertTrue($result->ok, (string)$result->error);
        $custoAntes = (float)$result->context['custo_reboque'];

        $pedido = $pdo->query("SELECT custo_estimado FROM pedidos WHERE id = 401")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta($custoAntes, (float)$pedido['custo_estimado'], 0.01);

        $custoSemCredito = $this->custoBrutoSemCredito(401);
        $this->assertEqualsWithDelta($custoSemCredito - 15.00, $custoAntes, 0.01, 'Crédito de conversão (15.00) precisa ter sido abatido do custo bruto do reboque.');
    }

    public function testCreditoRespeitaTeto(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_percentual', '0.30')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_maximo', '40.00')");
        // Socorro no local pago: R$ 300 -> 30% seria R$90, mas o teto é
        // R$40. Destino bem mais longe que nos outros testes, pra garantir
        // que a tarifa bruta do reboque fique bem acima de R$90 — só assim
        // o teste realmente diferencia "aplicou 30%" de "aplicou o teto".
        $this->criarPedidoOnSitePago(402, 300.00);

        $result = ConversionService::decidirConversao(402, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.90, 'lng' => -47.20,
        ]);
        $this->assertTrue($result->ok, (string)$result->error);

        $custoSemCredito = $this->custoBrutoSemCredito(402);
        $this->assertGreaterThan(90.00, $custoSemCredito, 'Pré-condição do teste: a tarifa bruta precisa ser bem maior que os R$90 que 30% daria, senão o teste não distingue teto de percentual.');

        $pedido = $pdo->query("SELECT custo_estimado FROM pedidos WHERE id = 402")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta($custoSemCredito - 40.00, (float)$pedido['custo_estimado'], 0.01, 'Crédito nunca pode passar do teto configurado (40.00), mesmo quando 30% do valor pago seria maior.');
    }

    public function testSemConfiguracaoUsaDefaultDeProducao(): void
    {
        // Config real de produção grava as duas chaves juntas (ver
        // tools/aplicar_comissao_20_80_liquido.php) — mas se faltar, o
        // código precisa ter defaults seguros (30% / R$40), não 0%.
        $this->criarPedidoOnSitePago(403, 50.00);

        $result = ConversionService::decidirConversao(403, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($result->ok, (string)$result->error);

        $pdo = getPDO();
        $pedido = $pdo->query("SELECT custo_estimado FROM pedidos WHERE id = 403")->fetch(PDO::FETCH_ASSOC);
        $custoSemCredito = $this->custoBrutoSemCredito(403);

        $this->assertEqualsWithDelta($custoSemCredito - 15.00, (float)$pedido['custo_estimado'], 0.01, 'Default de produção (30%, teto R$40) precisa ser aplicado mesmo sem config explícita.');
    }

    public function testSemPagamentoAprovadoBloqueiaConversao(): void
    {
        // §HIBRIDO-COMPLEMENTAR-01: sem pagamento aprovado prévio não é mais
        // "crédito zero e segue em frente" — é bloqueio, porque indica
        // inconsistência real (nenhum socorro no local chega até aqui sem
        // ter sido pago). Cobrar o complementar "no escuro" seria pior que
        // recusar.
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, attendance_mode,
                        lat_origem, lng_origem, endereco_origem)
                     VALUES (404, 'conversao_reboque_pendente', 0, 1, 1, 'ON_SITE', -23.55, -46.63, 'Origem')");

        $result = ConversionService::decidirConversao(404, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);

        $this->assertFalse($result->ok, 'Conversão sem pagamento aprovado prévio deveria ser bloqueada, não seguir com crédito zero.');
        $pedido = $pdo->query("SELECT status FROM pedidos WHERE id = 404")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('conversao_aprovada_cliente', $pedido['status'], 'A transição de status inicial já aconteceu (cliente aprovou) — só a cobrança complementar é que é bloqueada; o pedido não deve avançar além disso nem voltar sozinho.');
    }

    public function testChamadaDuplicadaNaoArquivaNemAplicaCreditoDeNovo(): void
    {
        // §CONCORRENCIA-01: simula a janela de corrida real — duas
        // chamadas concorrentes de decidirConversao() para o MESMO pedido,
        // ambas passando pela transição 'conversao_reboque_pendente' ->
        // 'conversao_aprovada_cliente' (a primeira transiciona de verdade, a
        // segunda encontra o pedido JÁ em 'conversao_aprovada_cliente' e cai
        // no caminho noop de transition() — from===to). Sem o guard de
        // noop, a segunda chamada arquivaria o pagamento (já arquivado) e
        // abateria o crédito de novo.
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_percentual', '0.30')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('credito_conversao_maximo', '40.00')");
        $this->criarPedidoOnSitePago(405, 50.00);

        $primeira = ConversionService::decidirConversao(405, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($primeira->ok, (string)$primeira->error);
        $custoAposPrimeira = (float)$pdo->query("SELECT custo_estimado FROM pedidos WHERE id = 405")->fetchColumn();

        // Reproduz a janela de corrida: volta o status pra
        // 'conversao_aprovada_cliente' (onde a 2ª chamada concorrente teria
        // parado, na prática, ANTES do resto do processamento da 1ª
        // terminar) sem desfazer o arquivamento nem o crédito já aplicado —
        // exatamente o estado que uma chamada duplicada real encontraria.
        $pdo->exec("UPDATE pedidos SET status = 'conversao_aprovada_cliente' WHERE id = 405");

        $segunda = ConversionService::decidirConversao(405, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($segunda->ok, (string)$segunda->error);
        $this->assertSame('ja_processada', $segunda->context['conversao'] ?? null);

        $custoAposSegunda = (float)$pdo->query("SELECT custo_estimado FROM pedidos WHERE id = 405")->fetchColumn();
        $this->assertEqualsWithDelta($custoAposPrimeira, $custoAposSegunda, 0.01, 'Chamada duplicada não pode reprocessar/abater o crédito de novo.');

        $qtdArquivados = (int)$pdo->query("SELECT COUNT(*) FROM pagamentos_arquivados WHERE pedido_id = 405")->fetchColumn();
        $this->assertSame(1, $qtdArquivados, 'O pagamento original só pode ter sido arquivado UMA vez.');
    }
}
