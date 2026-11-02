<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Diagnostico/DiagnosticoService.php
// ROADMAP socorro automotivo - Etapa 5 (diagn�stico e or�amento complementar).
//
// Orquestra as transi��es do meio do fluxo ON_SITE/HYBRID (OnSiteFlowDefinition,
// Etapa 3) em cima de PedidoTransitionService::transition() - mesma engrenagem
// de valida��o (SELECT FOR UPDATE, PedidoStateMachine::canTransition,
// autorizeTransition) usada pelo fluxo de reboque, sem duplicar l�gica de
// concorr�ncia/autoriza��o.
//
// diagnostico_iniciado ? diagnostico_concluido � sempre 2 passos (regra do
// OnSiteFlowDefinition); concluirDiagnostico() faz os dois em sequ�ncia e
// decide o pr�ximo estado real conforme o resultado do diagn�stico:
//   RESOLVIDO_SEM_ORCAMENTO ? em_execucao_servico direto
//   REQUER_ORCAMENTO        ? autorizacao_servico_pendente (aguarda cliente)
//   REQUER_REBOQUE          ? conversao_reboque_pendente (Etapa 7 assume daqui)

require_once __DIR__ . '/../Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionResult.php';
require_once __DIR__ . '/../../Models/PedidoDiagnostico.php';
require_once __DIR__ . '/../../Models/PedidoOrcamento.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../Logger.php';

final class DiagnosticoService
{
    /** no_local ? diagnostico_iniciado. S� o guincho designado no pedido pode iniciar. */
    public static function iniciarDiagnostico(int $pedidoId, int $guinchoId, int $actorId): PedidoTransitionResult
    {
        $result = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, 'diagnostico_iniciado', $guinchoId
        ));

        if ($result->ok) {
            Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'iniciarDiagnostico', 'diagnostico',
                "Diagn�stico iniciado - pedido #{$pedidoId} por guincho #{$guinchoId}",
                ['pedido_id' => $pedidoId, 'guincho_id' => $guinchoId]);
        }

        return $result;
    }

    /**
     * @param array<int, array{descricao:string, valor:float}> $itensOrcamento
     *   S� usado quando $resultado === REQUER_ORCAMENTO; ignorado nos outros casos.
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
            return PedidoTransitionResult::failure('Resultado de diagn�stico inv�lido.');
        }

        // Passo 1 (sempre): diagnostico_iniciado ? diagnostico_concluido.
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
                return PedidoTransitionResult::failure('Informe ao menos um item para o or�amento complementar.');
            }
            PedidoOrcamento::criar($pedidoId, $diagnosticoId, $itensOrcamento);
        }

        $step2 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, $proximoStatus, $guinchoId
        ));

        if ($step2->ok) {
            Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'concluirDiagnostico', 'diagnostico',
                "Diagn�stico conclu�do - pedido #{$pedidoId}: {$resultado} -> {$proximoStatus}",
                ['pedido_id' => $pedidoId, 'guincho_id' => $guinchoId, 'resultado' => $resultado, 'proximo_status' => $proximoStatus]);
        }

        return $step2;
    }

    /**
     * Cliente aprova ou recusa o or�amento complementar.
     * Aprovado ? autorizacao_servico_pendente ? em_execucao_servico.
     * Recusado ? or�amento fica RECUSADO, pedido permanece em
     * autorizacao_servico_pendente (decis�o de prop�sito: n�o cancela nem
     * cobra automaticamente - resolu��o manual via admin/Demanda, mesmo
     * princ�pio j� adotado para falha de pagamento).
     */
    public static function decidirOrcamento(int $pedidoId, int $clienteId, bool $aprovado): PedidoTransitionResult
    {
        $orcamento = PedidoOrcamento::buscarPorPedido($pedidoId);
        if (!$orcamento || $orcamento['status'] !== PedidoOrcamento::PENDENTE) {
            return PedidoTransitionResult::failure('N�o h� or�amento pendente para este pedido.');
        }

        $novoStatus = $aprovado ? PedidoOrcamento::APROVADO : PedidoOrcamento::RECUSADO;
        PedidoOrcamento::decidir($pedidoId, $novoStatus);

        Logger::log(Logger::LEVEL_INFO, 'DiagnosticoService', 'decidirOrcamento', 'diagnostico',
            "Or�amento " . ($aprovado ? 'aprovado' : 'recusado') . " pelo cliente - pedido #{$pedidoId}",
            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'status' => $novoStatus]);

        if (!$aprovado) {
            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['orcamento' => 'recusado']);
        }

        $itens = $orcamento['itens'] ?? [];
        // Todo or�amento complementar (pe�a ou m�o de obra) exige cobran�a
        // expl�cita antes da execu��o. O pagamento inicial do socorro n�o �
        // reutilizado nem sobrescrito.
        $destino = 'aguardando_pagamento_orcamento';
        $transicao = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'cliente', $clienteId, $pedidoId, $destino
        ));

        // �COBERTURA-RAIO-01 (06/08/2026): baixa de estoque s� acontece
        // DEPOIS da transi��o de estado ter sucesso - nunca consome pe�a de
        // um pedido que, por algum motivo de concorr�ncia/valida��o, n�o
        // conseguiu avan�ar pra em_execucao_servico. S� itens com
        // produto_id (ver PedidoOrcamento::criar) consomem estoque; itens
        // de m�o de obra/servi�o s�o ignorados aqui, como sempre foram.
        //
        // Decis�o de produto registrada (falta de estoque N�O bloqueia o
        // atendimento): o servi�o j� foi fisicamente autorizado pelo
        // cliente e o prestador pode ter a pe�a em m�os mesmo com o
        // cadastro de estoque desatualizado - bloquear a transi��o de
        // estado por causa disso trocaria uma diverg�ncia de invent�rio por
        // uma trava operacional no atendimento real. Em vez de bloquear,
        // cada falha de baixa (saldo insuficiente) � logada em WARN com os
        // dados do pedido/produto pra reconcilia��o manual do admin depois.
        if ($transicao->ok) {
            // A aprova��o do or�amento precisa deixar uma cobran�a itemizada
            // e idempotente para a pe�a. Isto registra o que o cliente
            // autorizou, sem reutilizar a cobran�a inicial do pedido e sem
            // liberar Pix/repasse: a elegibilidade continua dependente das
            // evid�ncias do servi�o.
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
                "Or�amento aprovado sem guincho_id no pedido - baixa de estoque pulada - pedido #{$pedidoId}",
                ['pedido_id' => $pedidoId]);
            return;
        }

        require_once __DIR__ . '/../Estoque/EstoqueService.php';

        foreach ($itens as $item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            if ($produtoId <= 0) {
                continue; // item de m�o de obra/servi�o - n�o consome estoque.
            }
            $qtd = max(1, (int)($item['quantidade'] ?? 1));
            $ok = EstoqueService::baixarPorPedido($providerId, $produtoId, $pedidoId, $qtd,
                "Or�amento aprovado - pedido #{$pedidoId}: " . (string)($item['descricao'] ?? ''));

            if (!$ok) {
                Logger::log(Logger::LEVEL_WARN, 'DiagnosticoService', 'baixarEstoqueDoOrcamento', 'estoque',
                    "Falha ao baixar estoque (saldo insuficiente ou erro) - pedido #{$pedidoId}, produto #{$produtoId}, qtd {$qtd} - n�o bloqueia o atendimento, requer reconcilia��o manual",
                    ['pedido_id' => $pedidoId, 'provider_id' => $providerId, 'produto_id' => $produtoId, 'quantidade' => $qtd]);
            }
        }
    }

    /** em_execucao_servico ? teste_final. O prestador terminou o servi�o, aguardando confirma��o de resultado. */
    public static function concluirExecucao(int $pedidoId, int $guinchoId, int $actorId): PedidoTransitionResult
    {
        return PedidoTransitionService::transition(new PedidoTransitionRequest(
            'guincho', $actorId, $pedidoId, 'teste_final', $guinchoId
        ));
    }

    /**
     * teste_final ? concluido (funcionou) ou conversao_reboque_pendente
     * (n�o funcionou, precisa de reboque - Etapa 7 assume a partir da�).
     * 'concluido' passa pelas mesmas geofence/evid�ncia j� exigidas para
     * o fluxo de reboque (validatePreconditions em PedidoTransitionService
     * n�o distingue attendance_mode) - o contexto de evid�ncia �
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
