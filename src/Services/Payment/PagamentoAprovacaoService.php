<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/Pagamento.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../../Services/NotificacaoService.php';
require_once __DIR__ . '/../../Services/Logger.php';
require_once __DIR__ . '/../../Services/AuditTrailService.php';
require_once __DIR__ . '/../Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../CoberturaService.php';

/**
 * Ponto único de aprovação de pagamento — extraído de
 * WebhookController::aprovarPagamento() (§IDEMPOTENCIA-01/02, transação
 * atômica via PedidoTransitionService::approvePayment()) para ser
 * reutilizado tanto pelo webhook assíncrono (MercadoPago/PagSeguro) quanto
 * pelo checkout transparente novo, cuja resposta síncrona da API (POST
 * /v1/payments do MP, POST /v2/transactions do PagSeguro) já É o mesmo
 * objeto de pagamento que o webhook buscaria depois — não há motivo pra
 * esperar o webhook chegar pra aprovar quando a API já respondeu
 * "aprovado" na hora, e duplicar a lógica de aprovação em dois lugares é
 * exatamente o tipo de divergência que a constituição do projeto proíbe
 * (idempotência/transição de pedido tem que ter UM caminho só).
 *
 * O webhook, quando chegar depois pra essa mesma transação, vai bater no
 * curto-circuito de idempotência normal (pagamento já 'aprovado') e não
 * fazer nada — comportamento correto e já validado em produção.
 */
final class PagamentoAprovacaoService
{
    /**
     * @return array{ok: bool, erro: ?string}
     */
    public static function aprovar(int $pedidoId, string $idExterno, string $payload, string $origem): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT p.status, p.custo_estimado, u.nome AS cliente_nome, u.email AS cliente_email
                 FROM pedidos p JOIN usuarios u ON u.id = p.cliente_id
                 WHERE p.id = ?"
            );
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Erro ao buscar pedido {$pedidoId}: " . $e->getMessage(),
                ['pedido_id' => $pedidoId]
            );
            return ['ok' => false, 'erro' => 'Erro interno ao buscar o pedido.'];
        }

        // §HIBRIDO-COMPLEMENTAR-01 (27/07/2026, achado em revisão):
        // 'aguardando_pagamento_reboque_hibrido' precisa ser aceito aqui
        // também — sem isso, o webhook REAL e legítimo da cobrança
        // complementar do caminho híbrido era rejeitado bem antes de chegar
        // em PedidoTransitionService::approvePayment() (que já sabia lidar
        // com os dois status), porque este gate comparava só contra a
        // string 'aguardando_pagamento'.
        $statusValidos = ['aguardando_pagamento', 'aguardando_pagamento_reboque_hibrido'];
        if (!$pedido || !in_array($pedido['status'] ?? '', $statusValidos, true)) {
            Logger::log(Logger::LEVEL_INFO, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Pedido {$pedidoId} não está aguardando pagamento — ignorado.",
                ['pedido_id' => $pedidoId, 'status' => $pedido['status'] ?? 'não encontrado']
            );
            return ['ok' => false, 'erro' => 'Pedido não está aguardando pagamento.'];
        }

        // §WEBHOOK-ARQUIVADO-01 (27/07/2026, achado em revisão): a
        // idempotência de webhook original (WebhookController) só olhava a
        // linha VIVA de `pagamentos` — um webhook atrasado/reenviado do
        // pagamento ORIGINAL (socorro no local, já arquivado por
        // Pagamento::arquivarParaCobrancaComplementar() quando o pedido foi
        // convertido) não era encontrado ali (id_externo foi limpo no
        // reset), passava pela idempotência, e aprovava a linha viva — que
        // a essa altura já é a cobrança COMPLEMENTAR — usando o
        // payload/id_externo do pagamento ANTIGO. Checa aqui, no ponto único
        // de aprovação (cobre webhook E checkout transparente síncrono):
        // se este id_externo já pertence a um ciclo de cobrança ARQUIVADO
        // deste ou de outro pedido, é garantidamente um evento de um
        // pagamento já encerrado — ignora como duplicata.
        $arquivado = Pagamento::buscarArquivadoPorIdExterno($idExterno);
        if ($arquivado !== null) {
            Logger::log(Logger::LEVEL_WARN, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Pedido {$pedidoId}: {$idExterno} pertence a um pagamento já ARQUIVADO (ciclo de cobrança encerrado) — ignorado para não aprovar a cobrança viva com dados de um pagamento antigo.",
                ['pedido_id' => $pedidoId, 'id_externo' => $idExterno, 'pagamento_arquivado_pedido_id' => $arquivado['pedido_id'] ?? null]
            );
            return ['ok' => true, 'erro' => null];
        }

        $pag = Pagamento::buscarPorPedido($pedidoId);
        if (!$pag) {
            Logger::log(Logger::LEVEL_ERROR, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Pagamento não encontrado para pedido {$pedidoId}.",
                ['pedido_id' => $pedidoId, 'id_externo' => $idExterno]
            );
            return ['ok' => false, 'erro' => 'Pagamento não encontrado para o pedido.'];
        }

        if ((string)($pedido['status'] ?? '') === 'aguardando_pagamento'
            && (string)($pedido['attendance_mode'] ?? '') === 'ON_SITE'
            && (int)($pedido['service_type_id'] ?? 0) > 0
            && empty($pedido['incidente_id'])) {
            $diagnostico = CoberturaService::diagnosticarAtendimento($pedido);
            if (($diagnostico['pode_cobrar'] ?? true) !== true) {
                $mensagem = (string)($diagnostico['mensagem'] ?? 'No momento não há cobertura para esta ocorrência.');
                Logger::log(Logger::LEVEL_WARN, 'PagamentoAprovacaoService', 'aprovar', $origem,
                    'Cobertura insuficiente para cobrar o pedido.',
                    ['pedido_id' => $pedidoId, 'id_externo' => $idExterno, 'diagnostico' => $diagnostico]
                );
                return ['ok' => false, 'erro' => $mensagem];
            }
        }

        // §IDEMPOTENCIA-02: pagamento do pedido já aprovado por outro
        // evento (webhook chegou antes da resposta síncrona voltar, ou
        // vice-versa) — idêntico ao curto-circuito do WebhookController.
        if (($pag['status'] ?? '') === 'aprovado') {
            Logger::log(Logger::LEVEL_INFO, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Pedido {$pedidoId}: pagamento já aprovado (id_externo={$pag['id_externo']}) — ignorando {$idExterno}.",
                ['pedido_id' => $pedidoId, 'id_externo_existente' => $pag['id_externo'] ?? null]
            );
            return ['ok' => true, 'erro' => null];
        }

        $transition = PedidoTransitionService::approvePayment($pedidoId, $idExterno, $payload);
        if (!$transition->ok) {
            Logger::log(Logger::LEVEL_WARN, 'PagamentoAprovacaoService', 'aprovar', $origem,
                'Pagamento recebido, mas a transição do pedido foi recusada.',
                ['pedido_id' => $pedidoId, 'id_externo' => $idExterno, 'erro' => $transition->error]
            );
            return ['ok' => false, 'erro' => $transition->error ?? 'Transição do pedido recusada.'];
        }

        $total = (float)$pedido['custo_estimado'];

        AuditTrailService::evento('pagamento_aprovado', 'PagamentoAprovacaoService', 'aprovar', [
            'pedido_id'  => $pedidoId,
            'id_externo' => $idExterno,
            'valor'      => $total,
            'origem'     => $origem,
        ]);

        Logger::log(Logger::LEVEL_INFO, 'PagamentoAprovacaoService', 'aprovar', $origem,
            'Pagamento ' . $idExterno . ' aprovado para pedido ' . $pedidoId . ' (origem: ' . $origem . '). Valor: R$' . number_format($total, 2, ',', '.') . '.',
            ['pedido_id' => $pedidoId, 'id_externo' => $idExterno]
        );

        try {
            $cliente = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];
            NotificacaoService::pedidoConfirmado($pedido, $cliente);
        } catch (Throwable $eNotif) {
            Logger::log(Logger::LEVEL_ERROR, 'PagamentoAprovacaoService', 'aprovar', $origem,
                "Falha ao notificar cliente: " . $eNotif->getMessage(),
                ['pedido_id' => $pedidoId]
            );
        }

        return ['ok' => true, 'erro' => null];
    }
}
