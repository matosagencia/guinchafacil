<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/Finance/PayoutLedgerEntry.php';

/**
 * src/Services/Payment/PayoutLedgerService.php
 * Pacote L1.7 — grava os lançamentos do ledger append-only nos pontos exatos
 * do fluxo onde dinheiro "nasce" (pagamento aprovado com split) ou "sai"
 * (repasse Pix confirmado ao guincho) ou é revertido (estorno).
 *
 * Todo método aqui espera rodar DENTRO da transação já aberta pelo chamador
 * (approvePayment, confirmarRepassePix, estornar), para que o lançamento
 * contábil seja atômico com a mudança de estado que o originou.
 */
class PayoutLedgerService
{
    /**
     * §SPLIT-LIQUIDO-01 (26/07/2026): $valorReservaGateway é a parcela do
     * valor bruto retida para taxa de gateway (Mercado Pago/PagSeguro) antes
     * de comissão/repasse incidirem — ver PedidoTransitionService::approvePayment.
     * Registrada como lançamento PRÓPRIO (não some do ledger) para que a
     * reconciliação contábil sempre feche: bruto = guincho + plataforma +
     * reserva_gateway. Parâmetro opcional (default 0.0) para não quebrar
     * chamadores existentes que ainda não passam esse valor.
     */
    public static function registrarSplitAprovado(PDO $pdo, int $pagamentoId, int $pedidoId, float $valorGuincho, float $valorPlataforma, string $idExterno = '', float $valorReservaGateway = 0.0): void
    {
        PayoutLedgerEntry::registrar($pdo, [
            'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
            'entry_type' => 'credito_guincho', 'valor' => $valorGuincho,
            'referencia_externa' => $idExterno,
        ]);
        PayoutLedgerEntry::registrar($pdo, [
            'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
            'entry_type' => 'credito_plataforma', 'valor' => $valorPlataforma,
            'referencia_externa' => $idExterno,
        ]);
        if ($valorReservaGateway > 0) {
            PayoutLedgerEntry::registrar($pdo, [
                'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
                'entry_type' => 'reserva_gateway', 'valor' => $valorReservaGateway,
                'referencia_externa' => $idExterno,
            ]);
        }
    }

    public static function registrarRepasseConcluido(PDO $pdo, int $pagamentoId, int $pedidoId, float $valorGuincho, string $idTransacaoPix): void
    {
        PayoutLedgerEntry::registrar($pdo, [
            'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
            'entry_type' => 'debito_repasse_guincho', 'valor' => $valorGuincho,
            'referencia_externa' => $idTransacaoPix,
        ]);
    }

    public static function registrarEstorno(PDO $pdo, int $pagamentoId, int $pedidoId, float $valorGuincho, float $valorPlataforma): void
    {
        if ($valorGuincho > 0) {
            PayoutLedgerEntry::registrar($pdo, [
                'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
                'entry_type' => 'estorno_credito_guincho', 'valor' => $valorGuincho,
            ]);
        }
        if ($valorPlataforma > 0) {
            PayoutLedgerEntry::registrar($pdo, [
                'pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId,
                'entry_type' => 'estorno_credito_plataforma', 'valor' => $valorPlataforma,
            ]);
        }
    }

    /**
     * Reconciliação: saldo líquido esperado do guincho = créditos - débitos - estornos.
     * Usado por testes e por telas administrativas de fechamento contábil.
     */
    public static function saldoLiquidoGuincho(int $pedidoId): float
    {
        $entries = PayoutLedgerEntry::listarPorPedido($pedidoId);
        $saldo = 0.0;
        foreach ($entries as $e) {
            $valor = (float)$e['valor'];
            $saldo += match ($e['entry_type']) {
                'credito_guincho' => $valor,
                'debito_repasse_guincho', 'estorno_credito_guincho' => -$valor,
                default => 0.0,
            };
        }
        return round($saldo, 2);
    }
}
