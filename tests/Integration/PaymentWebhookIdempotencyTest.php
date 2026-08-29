<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Models/Finance/PayoutLedgerEntry.php';

/**
 * Pacote L1.7 — simula dois webhooks entregues para o mesmo evento de pagamento
 * (retry do gateway, ou dois workers processando a mesma fila) chamando
 * PedidoTransitionService::approvePayment() duas vezes com o mesmo id_externo.
 * A segunda chamada deve ser rejeitada e o split/ledger não pode duplicar.
 */
final class PaymentWebhookIdempotencyTest extends TestCase
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

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (901, 'Cliente Webhook', 'cliente.webhook@example.com', 'hash', '11999991001', '90111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (901, 901, 'WHK1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    public function testDuplicateWebhookDoesNotDuplicatePaymentOrLedger(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (901, 'aguardando_pagamento', 100.00, 901, 901, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (901, 'mercadopago', 'pendente', 100.00)");

        // Primeira "entrega" do webhook.
        $primeira = PedidoTransitionService::approvePayment(901, 'mp_webhook_dup', '{"approved":true}');
        $this->assertTrue($primeira->ok, (string)$primeira->error);

        // Segunda entrega do MESMO evento (retry do gateway, ou dois workers concorrentes).
        // O pedido já saiu de 'aguardando_pagamento' na primeira aprovação, então a segunda
        // chamada é barrada pela guarda de status (mais cedo na função) — o resultado prático
        // é o mesmo: nenhuma segunda aprovação/split acontece.
        $segunda = PedidoTransitionService::approvePayment(901, 'mp_webhook_dup', '{"approved":true}');
        $this->assertFalse($segunda->ok);
        $this->assertStringContainsString('não está aguardando pagamento', (string)$segunda->error);

        // Pedido só avançou de status uma vez.
        $status = $pdo->query("SELECT status FROM pedidos WHERE id = 901")->fetchColumn();
        $this->assertSame('aguardando_guincho', $status);

        // O split (id_externo) não duplicou: continua havendo 1 linha de pagamento aprovada.
        $aprovados = (int)$pdo->query("SELECT COUNT(*) FROM pagamentos WHERE pedido_id = 901 AND status = 'aprovado'")->fetchColumn();
        $this->assertSame(1, $aprovados);

        // O ledger não duplicou: exatamente 1 crédito de guincho + 1 de plataforma.
        $entries = PayoutLedgerEntry::listarPorPedido(901);
        $creditosGuincho = array_filter($entries, fn($e) => $e['entry_type'] === 'credito_guincho');
        $creditosPlataforma = array_filter($entries, fn($e) => $e['entry_type'] === 'credito_plataforma');
        $this->assertCount(1, $creditosGuincho, 'Webhook duplicado não pode gerar um segundo crédito de guincho no ledger.');
        $this->assertCount(1, $creditosPlataforma, 'Webhook duplicado não pode gerar um segundo crédito de plataforma no ledger.');
    }
}
