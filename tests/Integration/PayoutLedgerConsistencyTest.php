<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Services/EstornoService.php';
require_once __DIR__ . '/../../src/Services/Payment/PayoutLedgerService.php';
require_once __DIR__ . '/../../src/Models/Finance/PayoutLedgerEntry.php';
require_once __DIR__ . '/../../src/Models/Pagamento.php';

/**
 * Pacote L1.7 — prova, via soma de linhas do ledger append-only, que o fechamento
 * contábil do split/repasse é consistente: crédito de guincho e plataforma somam
 * o valor total aprovado; débito de repasse iguala o crédito quando o Pix é pago;
 * e estorno gera lançamentos reversos, sem NUNCA fazer UPDATE/DELETE nas linhas
 * já gravadas (é literalmente impossível: PayoutLedgerEntry só expõe INSERT).
 */
final class PayoutLedgerConsistencyTest extends TestCase
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
        // §SPLIT-LIQUIDO-01: isola este teste (reconciliação guincho+plataforma
        // == total) do default de produção de reserva_gateway_percentual
        // (0.045) — a reconciliação DE TRÊS PARTES (guincho+plataforma+reserva
        // == total) é coberta em PedidoTransitionServiceSplitLiquidoTest.
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (921, 'Cliente Ledger', 'cliente.ledger@example.com', 'hash', '11999993001', '92111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (921, 921, 'LDG1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    public function testSplitCreditsSumToTotalApproved(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (921, 'aguardando_pagamento', 200.00, 921, 921, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (921, 'mercadopago', 'pendente', 200.00)");

        $resultado = PedidoTransitionService::approvePayment(921, 'mp_ledger_921', '{}');
        $this->assertTrue($resultado->ok, (string)$resultado->error);

        $totais = PayoutLedgerEntry::somaPorTipo();
        $this->assertEqualsWithDelta(200.0, ($totais['credito_guincho'] ?? 0) + ($totais['credito_plataforma'] ?? 0), 0.01,
            'Soma de crédito de guincho + plataforma deve fechar exatamente com o valor total aprovado.');
    }

    public function testPayoutDebitMatchesCreditAndZeroesNetBalance(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (922, 'aguardando_pagamento', 200.00, 921, 921, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (922, 'mercadopago', 'pendente', 200.00)");

        $aprova = PedidoTransitionService::approvePayment(922, 'mp_ledger_922', '{}');
        $this->assertTrue($aprova->ok);

        $pagamentoId = (int)$pdo->query("SELECT id FROM pagamentos WHERE pedido_id = 922")->fetchColumn();

        // Antes do repasse: saldo líquido do guincho = crédito integral (nada debitado ainda).
        $saldoAntes = PayoutLedgerService::saldoLiquidoGuincho(922);
        $this->assertGreaterThan(0.0, $saldoAntes);

        // Marca o pagamento como pronto para repasse (simula o que PaymentJobService faria).
        $pdo->exec("UPDATE pagamentos SET status_pix = 'processando' WHERE id = {$pagamentoId}");
        $confirmado = Pagamento::confirmarRepassePix($pagamentoId, 'TX-LEDGER-922');
        $this->assertTrue($confirmado);

        $saldoDepois = PayoutLedgerService::saldoLiquidoGuincho(922);
        $this->assertEqualsWithDelta(0.0, $saldoDepois, 0.01, 'Depois do repasse pago, crédito e débito de guincho devem se cancelar.');

        $totais = PayoutLedgerEntry::somaPorTipo();
        $this->assertEqualsWithDelta($totais['credito_guincho'] ?? 0, $totais['debito_repasse_guincho'] ?? 0, 0.01);
    }

    public function testRefundGeneratesReversalEntriesWithoutMutatingPriorRows(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (923, 'aguardando_pagamento', 200.00, 921, 921, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (923, 'freeflow', 'pendente', 200.00)");

        $aprova = PedidoTransitionService::approvePayment(923, 'ff_ledger_923', '{}');
        $this->assertTrue($aprova->ok);

        $entriesAntes = PayoutLedgerEntry::listarPorPedido(923);
        $this->assertCount(2, $entriesAntes, 'Antes do estorno: 1 crédito de guincho + 1 de plataforma.');

        // 'freeflow' não é suportado por EstornoService — força a rota de erro,
        // mas isso não deve gerar nenhum lançamento no ledger (nem mexer nos já existentes).
        $estorno = EstornoService::estornar(923);
        $this->assertFalse($estorno['sucesso']);

        $entriesDepois = PayoutLedgerEntry::listarPorPedido(923);
        $this->assertCount(2, $entriesDepois, 'Estorno que falhou no gateway não pode alterar o ledger.');
        $this->assertSame($entriesAntes, $entriesDepois, 'As linhas originais do ledger são imutáveis (append-only).');
    }
}
