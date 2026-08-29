<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Conversion/ConversionService.php';
require_once __DIR__ . '/../../src/Services/Payment/PagamentoAprovacaoService.php';

/**
 * §WEBHOOK-ARQUIVADO-01 (27/07/2026, achado em revisão de código): depois
 * que ConversionService arquiva o pagamento aprovado original e reseta a
 * linha viva pra cobrança complementar, um webhook ATRASADO do pagamento
 * ORIGINAL (reenvio do gateway, ou apenas latência) não era pego pela
 * idempotência de PagamentoAprovacaoService/WebhookController — ela só
 * olhava a linha viva de `pagamentos`, onde o id_externo antigo já tinha
 * sido limpo pelo reset. O webhook atrasado então aprovava a linha viva
 * (a cobrança COMPLEMENTAR) usando o payload/id_externo do pagamento velho,
 * sem o cliente ter pago o complementar de fato.
 *
 * Também cobre um segundo bug encontrado na mesma revisão: o gate de status
 * em PagamentoAprovacaoService::aprovar() só aceitava a string exata
 * 'aguardando_pagamento' — o que rejeitava o webhook LEGÍTIMO da cobrança
 * complementar no caminho híbrido (status
 * 'aguardando_pagamento_reboque_hibrido') antes mesmo de chegar em
 * PedidoTransitionService::approvePayment(), que já sabia lidar com os dois.
 */
final class PagamentoAprovacaoServiceArquivadoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach ([
            'pedido_idempotency', 'pagamentos_arquivados', 'pagamentos', 'payout_ledger_entries',
            'pedidos', 'veiculos', 'usuarios', 'guinchos', 'provider_capabilities', 'service_types', 'configuracoes',
        ] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.webhook@example.com', 'hash', '11999999999', '11111111111', 'cliente')");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'WHK1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
    }

    private function criarPedidoConvertidoNaoHibrido(int $id): string
    {
        $pdo = getPDO();
        $idExternoOriginal = 'mp_original_' . $id;
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, attendance_mode,
                        lat_origem, lng_origem, endereco_origem)
                     VALUES ({$id}, 'conversao_reboque_pendente', 50.00, 1, 1, 'ON_SITE', -23.55, -46.63, 'Origem')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total, id_externo) VALUES
                     ({$id}, 'mercadopago', 'aprovado', 50.00, '{$idExternoOriginal}')");

        $conversao = ConversionService::decidirConversao($id, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        if (!$conversao->ok) {
            throw new RuntimeException('Falha no setup do teste: conversão não aprovou — ' . $conversao->error);
        }

        return $idExternoOriginal;
    }

    public function testWebhookAtrasadoDoPagamentoOriginalArquivadoNaoAprovaComplementar(): void
    {
        $idExternoOriginal = $this->criarPedidoConvertidoNaoHibrido(601);
        $pdo = getPDO();

        // Confere a pré-condição do bug: o pedido está de volta em
        // 'aguardando_pagamento' (mesma string de antes da conversão) e o
        // id_externo antigo só existe mais em pagamentos_arquivados.
        $pedidoAntes = $pdo->query("SELECT status FROM pedidos WHERE id = 601")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento', $pedidoAntes['status']);
        $this->assertNotNull(Pagamento::buscarArquivadoPorIdExterno($idExternoOriginal));
        $this->assertFalse(Pagamento::buscarPorIdExterno($idExternoOriginal));

        // Webhook atrasado chega carregando o id_externo do pagamento
        // ORIGINAL (já arquivado) — não pode aprovar a cobrança complementar.
        $resultado = PagamentoAprovacaoService::aprovar(601, $idExternoOriginal, '{}', 'webhook');
        $this->assertTrue($resultado['ok'], 'Webhook de pagamento arquivado deve ser ignorado silenciosamente (ok=true, sem erro), não falhar.');

        $pedidoDepois = $pdo->query("SELECT status FROM pedidos WHERE id = 601")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento', $pedidoDepois['status'], 'O pedido NÃO pode ter avançado — o cliente ainda não pagou o complementar de verdade.');

        $pagamentoVivo = $pdo->query("SELECT status, id_externo FROM pagamentos WHERE pedido_id = 601")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('pendente', $pagamentoVivo['status'], 'A cobrança complementar continua pendente — não foi aprovada com dados do pagamento antigo.');
        $this->assertNotSame($idExternoOriginal, $pagamentoVivo['id_externo']);
    }

    public function testWebhookLegitimoDoComplementarAindaFuncionaAposArquivamento(): void
    {
        // Garante que a correção do bug acima não bloqueou o caso normal:
        // o webhook de verdade da cobrança complementar (id_externo NOVO,
        // nunca arquivado) precisa continuar aprovando o pedido.
        $this->criarPedidoConvertidoNaoHibrido(602);
        $pdo = getPDO();

        $idExternoComplementar = 'mp_complementar_602';
        $resultado = PagamentoAprovacaoService::aprovar(602, $idExternoComplementar, '{}', 'webhook');
        $this->assertTrue($resultado['ok'], (string)($resultado['erro'] ?? ''));

        $pedidoDepois = $pdo->query("SELECT status FROM pedidos WHERE id = 602")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedidoDepois['status'], 'Pagamento complementar de verdade precisa avançar o pedido normalmente.');
    }

    public function testWebhookLegitimoDoCaminhoHibridoNaoEhMaisRejeitadoPeloGateDeStatus(): void
    {
        // §HIBRIDO-COMPLEMENTAR-01: antes da correção, este teste falhava
        // porque o gate de status em PagamentoAprovacaoService::aprovar()
        // só aceitava 'aguardando_pagamento' — rejeitando de cara o
        // pagamento aprovado de um pedido em
        // 'aguardando_pagamento_reboque_hibrido', mesmo sendo legítimo.
        $pdo = getPDO();
        $pdo->exec("INSERT INTO service_types (id, code, name, attendance_mode, active) VALUES
            (1, 'TOW_CAR', 'Reboque Carro', 'TOWING', 1)");
        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (2, 'Guincho Teste', 'guincho.webhook@example.com', 'hash', '11988888888', '22222222222', 'guincho')");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (20, 2, 1, 0)");
        $pdo->exec("INSERT INTO provider_capabilities
                        (provider_id, service_type_id, enabled, approval_status, created_at, updated_at)
                     VALUES (20, 1, 1, 'APPROVED', datetime('now'), datetime('now'))");

        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, attendance_mode,
                        lat_origem, lng_origem, endereco_origem)
                     VALUES (603, 'conversao_reboque_pendente', 50.00, 1, 1, 20, 'ON_SITE', -23.55, -46.63, 'Origem')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES
                     (603, 'mercadopago', 'aprovado', 50.00)");

        $conversao = ConversionService::decidirConversao(603, 1, true, [
            'endereco' => 'Destino Teste', 'lat' => -23.56, 'lng' => -46.64,
        ]);
        $this->assertTrue($conversao->ok, (string)$conversao->error);
        $pedidoAntes = $pdo->query("SELECT status FROM pedidos WHERE id = 603")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_reboque_hibrido', $pedidoAntes['status']);

        $resultado = PagamentoAprovacaoService::aprovar(603, 'mp_hibrido_603', '{}', 'webhook');
        $this->assertTrue($resultado['ok'], (string)($resultado['erro'] ?? ''));

        $pedidoDepois = $pdo->query("SELECT status FROM pedidos WHERE id = 603")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('preparacao_veiculo', $pedidoDepois['status']);
    }
}
