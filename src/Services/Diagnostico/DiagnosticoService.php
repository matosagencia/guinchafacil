<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Diagnostico/DiagnosticoService.php
// ROADMAP socorro automotivo — Etapa 5 (diagnóstico e orçamento complementar).
//
// Orquestra as transições do meio do fluxo ON_SITE/HYBRID (OnSiteFlowDefinition,
// Etapa 3) em cima de PedidoTransitionService::transition() — mesma engrenagem
// de validação (SELECT FOR UPDATE, PedidoStateMachine::canTransition,
// autorizeTransition) usada pelo fluxo de reboque, sem duplicar lógica de
// concorrência/autorização.
//
// diagnostico_iniciado → diagnostico_concluido é sempre 2 passos (regra do
// OnSiteFlowDefinition); concluirDiagnostico() faz os dois em sequência e
// decide o próximo estado real conforme o resultado do diagnóstico:
//   RESOLVIDO_SEM_ORCAMENTO → em_execucao_servico direto
//   REQUER_ORCAMENTO        → autorizacao_servico_pendente (aguarda cliente)
//   REQUER_REBOQUE          → conversao_reboque_pendente (Etapa 7 assume daqui)

require_once __DIR__ . '/../Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionResult.php';
require_once __DIR__ . '/../../Models/PedidoDiagnostico.php';
require_once __DIR__ . '/../../Models/PedidoOrcamento.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../Logger.php';

final class DiagnosticoService
{
    /** no_local → diagnostico_iniciado. Só o guincho designado no pedido pode iniciar. */
    public static function iniciarDiagnostico(int $pedidoId, int $guinchoId, int $actorId): PedidoTransitionResult
    {
        $result = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, 'diagnostico_iniciado', $guinchoId
        ));

        if ($result->ok) {
            Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'iniciarDiagnostico', 'diagnostico',
                "Diagnóstico iniciado — pedido #{$pedidoId} por guincho #{$guinchoId}",
                ['pedido_id' => $pedidoId, 'guincho_id' => $guinchoId]);
        }

        return $result;
    }

    /**
     * @param array<int, array{descricao:string, valor:float}> $itensOrcamento
     *   Só usado quando $resultado === REQUER_ORCAMENTO; ignorado nos outros casos.
     */
    public static function concluirDiagnostico(
        int $pedidoId,
        int $guinchoId,
        int $actorId,
        string $resultado,
        ?string $descricao,
        array $itensOrcamento = []
    ): PedidoTransitionResult {
        if (!in_array($resultado, PedidoDiagnostico::RESULTADOS, true)) {
            return PedidoTransitionResult::failure('Resultado de diagnóstico inválido.');
        }

        // Passo 1 (sempre): diagnostico_iniciado → diagnostico_concluido.
        $step1 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, 'diagnostico_concluido', $guinchoId
        ));
        if (!$step1->ok) {
            return $step1;
        }

        $diagnosticoId = PedidoDiagnostico::registrar($pedidoId, $guinchoId, $resultado, $descricao);

        // Passo 2: destino real depende do resultado.
        $proximoStatus = match ($resultado) {
            PedidoDiagnostico::RESOLVIDO_SEM_ORCAMENTO => 'em_execucao_servico',
            PedidoDiagnostico::REQUER_ORCAMENTO => 'autorizacao_servico_pendente',
            PedidoDiagnostico::REQUER_REBOQUE => 'conversao_reboque_pendente',
        };

        if ($resultado === PedidoDiagnostico::REQUER_ORCAMENTO) {
            if (empty($itensOrcamento)) {
                return PedidoTransitionResult::failure('Informe ao menos um item para o orçamento complementar.');
            }
            PedidoOrcamento::criar($pedidoId, $diagnosticoId, $itensOrcamento);
        }

        $step2 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, $proximoStatus, $guinchoId
        ));

        if ($step2->ok) {
            Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'concluirDiagnostico', 'diagnostico',
                "Diagnóstico concluído — pedido #{$pedidoId}: {$resultado} -> {$proximoStatus}",
                ['pedido_id' => $pedidoId, 'guincho_id' => $guinchoId, 'resultado' => $resultado, 'proximo_status' => $proximoStatus]);
        }

        return $step2;
    }

    /**
     * Cliente aprova ou recusa o orçamento complementar.
     * Aprovado → autorizacao_servico_pendente → em_execucao_servico.
     * Recusado → orçamento fica RECUSADO, pedido permanece em
     * autorizacao_servico_pendente (decisão de propósito: não cancela nem
     * cobra automaticamente — resolução manual via admin/Demanda, mesmo
     * princípio já adotado para falha de pagamento).
     */
    public static function decidirOrcamento(int $pedidoId, int $clienteId, bool $aprovado): PedidoTransitionResult
    {
        $orcamento = PedidoOrcamento::buscarPorPedido($pedidoId);
        if (!$orcamento || $orcamento['status'] !== PedidoOrcamento::PENDENTE) {
            return PedidoTransitionResult::failure('Não há orçamento pendente para este pedido.');
        }

        $novoStatus = $aprovado ? PedidoOrcamento::APROVADO : PedidoOrcamento::RECUSADO;
        PedidoOrcamento::decidir($pedidoId, $novoStatus);

        Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'decidirOrcamento', 'diagnostico',
            "Orçamento " . ($aprovado ? 'aprovado' : 'recusado') . " pelo cliente — pedido #{$pedidoId}",
            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'status' => $novoStatus]);

        if (!$aprovado) {
            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['orcamento' => 'recusado']);
        }

        $itens = $orcamento['itens'] ?? [];
        // Todo orçamento complementar (peça ou mão de obra) exige cobrança
        // explícita antes da execução. O pagamento inicial do socorro não é
        // reutilizado nem sobrescrito.
        $destino = 'aguardando_pagamento_orcamento';
        $transicao = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'cliente', $clienteId, $pedidoId, $destino
        ));

        // §COBERTURA-RAIO-01 (06/08/2026): baixa de estoque só acontece
        // DEPOIS da transição de estado ter sucesso — nunca consome peça de
        // um pedido que, por algum motivo de concorrência/validação, não
        // conseguiu avançar pra em_execucao_servico. Só itens com
        // produto_id (ver PedidoOrcamento::criar) consomem estoque; itens
        // de mão de obra/serviço são ignorados aqui, como sempre foram.
        //
        // Decisão de produto registrada (falta de estoque NÃO bloqueia o
        // atendimento): o serviço já foi fisicamente autorizado pelo
        // cliente e o prestador pode ter a peça em mãos mesmo com o
        // cadastro de estoque desatualizado — bloquear a transição de
        // estado por causa disso trocaria uma divergência de inventário por
        // uma trava operacional no atendimento real. Em vez de bloquear,
        // cada falha de baixa (saldo insuficiente) é logada em WARN com os
        // dados do pedido/produto pra reconciliação manual do admin depois.
        if ($transicao->ok) {
            // A aprovação do orçamento precisa deixar uma cobrança itemizada
            // e idempotente para a peça. Isto registra o que o cliente
            // autorizou, sem reutilizar a cobrança inicial do pedido e sem
            // liberar Pix/repasse: a elegibilidade continua dependente das
            // evidências do serviço.
            self::registrarCobrancasDoOrcamento($pedidoId, $orcamento);
            if (!$temPeca) {
                self::baixarEstoqueDoOrcamento($pedidoId, $orcamento);
            }
        }

        return $transicao;
    }

    /** @param array<string, mixed> $orcamento */
    private static function registrarCobrancasDoOrcamento(int $pedidoId, array $orcamento): void
    {
        require_once __DIR__ . '/../../Models/Financial/OrderChargeItem.php';
        require_once __DIR__ . '/../../Models/Financial/ChargeCodes.php';

        $pedido = Pedido::buscarPorId($pedidoId);
        $providerId = (int)($pedido['guincho_id'] ?? 0);
        foreach (($orcamento['itens'] ?? []) as $index => $item) {
            $valor = round((float)($item['valor'] ?? 0), 2);
            if ($valor <= 0) {
                continue;
            }
            $produtoId = (int)($item['produto_id'] ?? 0);
            $tipo = $produtoId > 0 ? ChargeCodes::TYPE_PARTS_FEE : ChargeCodes::TYPE_LABOR_FEE;
            $fase = $produtoId > 0 ? ChargeCodes::PHASE_PARTS_SUPPLY : ChargeCodes::PHASE_ON_SITE_SERVICE;
            OrderChargeItem::criar([
                'order_id' => $pedidoId,
                'provider_id' => $providerId > 0 ? $providerId : null,
                'phase_code' => $fase,
                'charge_type' => $tipo,
                'description' => (string)($item['descricao'] ?? 'Item adicional aprovado'),
                'quantity' => max(1, (int)($item['quantidade'] ?? 1)),
                'unit_amount' => $valor,
                'gross_amount' => $valor * max(1, (int)($item['quantidade'] ?? 1)),
                'charge_status' => ChargeCodes::CHARGE_AWAITING_CUSTOMER_APPROVAL,
                'payable_status' => ChargeCodes::PAYABLE_PENDING_EVIDENCE,
                'calculation_version' => 'orcamento-v1',
                'calculation_context' => ['orcamento_id' => (int)($orcamento['id'] ?? 0), 'item_index' => (int)$index, 'produto_id' => $produtoId ?: null],
                'evidence_required' => true,
                'idempotency_key' => 'orcamento:' . $pedidoId . ':' . (int)($orcamento['id'] ?? 0) . ':' . (int)$index,
            ]);
        }
    }

    /** @param array<string, mixed> $orcamento resultado de PedidoOrcamento::buscarPorPedido() */
    private static function baixarEstoqueDoOrcamento(int $pedidoId, array $orcamento): void
    {
        $itens = $orcamento['itens'] ?? [];
        if (empty($itens)) {
            return;
        }

        $pedido = Pedido::buscarPorId($pedidoId);
        $providerId = (int)($pedido['guincho_id'] ?? 0);
        if ($providerId <= 0) {
            Logger::log(Logger::LEVEL_WARN, 'DiagnosticoService', 'baixarEstoqueDoOrcamento', 'estoque',
                "Orçamento aprovado sem guincho_id no pedido — baixa de estoque pulada — pedido #{$pedidoId}",
                ['pedido_id' => $pedidoId]);
            return;
        }

        require_once __DIR__ . '/../Estoque/EstoqueService.php';

        foreach ($itens as $item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            if ($produtoId <= 0) {
                continue; // item de mão de obra/serviço — não consome estoque.
            }
            $qtd = max(1, (int)($item['quantidade'] ?? 1));
            $ok = EstoqueService::baixarPorPedido($providerId, $produtoId, $pedidoId, $qtd,
                "Orçamento aprovado — pedido #{$pedidoId}: " . (string)($item['descricao'] ?? ''));

            if (!$ok) {
                Logger::log(Logger::LEVEL_WARN, 'DiagnosticoService', 'baixarEstoqueDoOrcamento', 'estoque',
                    "Falha ao baixar estoque (saldo insuficiente ou erro) — pedido #{$pedidoId}, produto #{$produtoId}, qtd {$qtd} — não bloqueia o atendimento, requer reconciliação manual",
                    ['pedido_id' => $pedidoId, 'provider_id' => $providerId, 'produto_id' => $produtoId, 'quantidade' => $qtd]);
            }
        }
    }

    /** em_execucao_servico → teste_final. O prestador terminou o serviço, aguardando confirmação de resultado. */
    public static function concluirExecucao(int $pedidoId, int $guinchoId, int $actorId): PedidoTransitionResult
    {
        return PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, 'teste_final', $guinchoId
        ));
    }

    /**
     * teste_final → concluido (funcionou) ou conversao_reboque_pendente
     * (não funcionou, precisa de reboque — Etapa 7 assume a partir daí).
     * 'concluido' passa pelas mesmas geofence/evidência já exigidas para
     * o fluxo de reboque (validatePreconditions em PedidoTransitionService
     * não distingue attendance_mode) — o contexto de evidência é
     * responsabilidade de quem chama (ver GuinchoController).
     */
    public static function confirmarResultadoFinal(
        int $pedidoId,
        int $guinchoId,
        int $actorId,
        bool $resolvido,
        array $context = []
    ): PedidoTransitionResult {
        $proximoStatus = $resolvido ? 'concluido' : 'conversao_reboque_pendente';

        return PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, $proximoStatus, $guinchoId, $context
        ));
    }
}
