<?php
declare(strict_types=1);

require_once __DIR__ . '/../../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionResult.php';
require_once __DIR__ . '/PedidoStateMachine.php';
require_once __DIR__ . '/../AuditTrailService.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../PedidoEventoService.php';
require_once __DIR__ . '/../POR/ProofOfRoadService.php';
require_once __DIR__ . '/../POR/GeofenceService.php';
require_once __DIR__ . '/../POR/PorThresholds.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../../Models/Guincho.php';
require_once __DIR__ . '/../../Models/Pagamento.php';
require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../Payment/PayoutLedgerService.php';
require_once __DIR__ . '/../Evidence/EvidenceService.php';
require_once __DIR__ . '/../DebugMode.php';
require_once __DIR__ . '/../GeoService.php';
require_once __DIR__ . '/../../Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../Dispatch/ProviderVehicleCompatibilityService.php';
require_once __DIR__ . '/../IncidenteService.php';
require_once __DIR__ . '/../EspecialistaPricingService.php';
require_once __DIR__ . '/../IncidenteFinanceiroService.php';
require_once __DIR__ . '/../ComissaoService.php';

final class PedidoTransitionService
{
    public static function approvePayment(int $pedidoId, string $idExterno, string $payload = '', int $actorId = 0): PedidoTransitionResult
    {
        $pdo = getPDO();
        $incidenteCriado = 0;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            // �HIBRIDO-COMPLEMENTAR-01 (27/07/2026): 'aguardando_pagamento_reboque_hibrido'
            // � o mesmo tipo de espera (cobran�a complementar de reboque
            // pendente), s� que o prestador h�brido j� est� vinculado - ver
            // ConversionService::finalizarCaminhoHibrido(). O destino
            // p�s-aprova��o � decidido logo abaixo, depois do split/ledger.
            $statusOrigem = (string)$pedido['status'];
            if (!in_array($statusOrigem, ['aguardando_pagamento', 'aguardando_pagamento_reboque_hibrido'], true)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido não está aguardando pagamento.', [
                    'status_atual' => $pedido['status'],
                ]);
            }

            $pag = Pagamento::buscarPorPedido($pedidoId);
            if (!$pag) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pagamento n�o encontrado para o pedido.');
            }
            if (($pag['status'] ?? '') === 'aprovado') {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pagamento deste pedido j� foi aprovado.', [
                    'pagamento_id' => $pag['id'] ?? null,
                ]);
            }

            // �SPLIT-LIQUIDO-01 (26/07/2026, corre��o de lacuna apontada pelo
            // usu�rio): antes desta corre��o, comissao_plataforma era aplicada
            // direto sobre o valor BRUTO pago pelo cliente, sem nunca
            // descontar a taxa do gateway (Mercado Pago/PagSeguro) - com
            // comiss�o de 21% + repasse de 80% + gateway de ~4%, a soma
            // ultrapassava 100% do valor recebido (preju�zo por pedido).
            // Agora a comiss�o incide sobre o valor L�QUIDO (j� descontada
            // uma reserva de gateway configur�vel), ent�o comiss�o + repasse
            // sempre somam exatamente 100% do l�quido - nunca estoura.
            // reserva_gateway_percentual � uma m�dia conservadora �nica (n�o
            // distingue Pix/cr�dito/parcelado por m�todo de pagamento ainda
            // - granularidade real por forma de pagamento fica para uma
            // itera��o futura, quando o m�todo efetivo do pagamento estiver
            // dispon�vel de forma confi�vel neste ponto do fluxo).
            $cfg = Configuracao::getAll();
            $rawComissao = isset($cfg['comissao_plataforma']) ? (float)$cfg['comissao_plataforma'] : 0.08;
            $comissao = $rawComissao > 1 ? $rawComissao / 100 : $rawComissao;
            $total = (float)($pedido['custo_final'] ?: ($pedido['custo_estimado'] ?? 0));

            // �5.7 - reserva din�mica por m�todo de pagamento efetivo.
            // O webhook traz o payload real do gateway; extra�mos o m�todo e usamos a
            // taxa espec�fica. Se n�o identificarmos (payload legado, formato novo),
            // cai no cr�dito � vista (pior caso comum).
            $metodoPagamento = self::detectarMetodoPagamento($payload);
            $reservaGateway  = self::reservaGatewayPara($cfg, $metodoPagamento);

            $valorReservaGateway = round($total * $reservaGateway, 2);
            // A comiss�o incide sobre o l�quido ap�s a reserva do gateway.
            // As tr�s parcelas continuam reconciliando exatamente com o bruto.
            $liquidoPosGateway   = round($total - $valorReservaGateway, 2);
            $valorPlataforma     = round($liquidoPosGateway * $comissao, 2);
            $valorGuincho        = round($liquidoPosGateway - $valorPlataforma, 2);
            Pagamento::aprovar((int)$pag['id'], $idExterno, $payload);
            Pagamento::atualizarSplit((int)$pag['id'], $valorGuincho, $valorPlataforma);
            PayoutLedgerService::registrarSplitAprovado($pdo, (int)$pag['id'], $pedidoId, $valorGuincho, $valorPlataforma, $idExterno, $valorReservaGateway);

            if ((string)($pedido['attendance_mode'] ?? '') === 'ON_SITE' && empty($pedido['incidente_id'])) {
                $svc = (int)($pedido['service_type_id'] ?? 0);
                $incidenteCriado = Incidente::criar([
                    'cliente_id' => (int)$pedido['cliente_id'], 'veiculo_id' => (int)$pedido['veiculo_id'],
                    'tipo_problema' => (string)$pedido['tipo_problema'], 'descricao_problema' => (string)$pedido['descricao_problema'],
                    'lat_origem' => (float)$pedido['lat_origem'], 'lng_origem' => (float)$pedido['lng_origem'],
                    'endereco_origem' => (string)$pedido['endereco_origem'], 'status' => 'procurando_oficina'
                ], $pdo);
                $pdo->prepare('UPDATE pedidos SET incidente_id=? WHERE id=?')->execute([$incidenteCriado, $pedidoId]);
            }

            $expMin = (int)($cfg['tempo_expiracao_min'] ?? 5);
            $raioInicial = (int)($cfg['raio_inicial_km'] ?? 10);

            // �HIBRIDO-COMPLEMENTAR-01 (27/07/2026): quando o pedido estava
            // aguardando o complementar de reboque no caminho H�BRIDO, o
            // destino padr�o � 'preparacao_veiculo' (mesmo prestador segue
            // vinculado, sem nova disputa de matching) - MAS s� depois de
            // revalidar aqui, no momento em que o pagamento de fato foi
            // aprovado, que o prestador ainda est� apto. Pode ter passado
            // tempo desde a decis�o de convers�o (cliente demorou pra pagar)
            // e o prestador ter sido suspenso/perdido a capacidade de
            // reboque nesse meio-tempo - nesse caso faz DOWNGRADE auditado
            // para a fila comum em vez de travar o pedido ou seguir com um
            // prestador inv�lido.
            $statusNovo = ((string)($pedido['attendance_mode'] ?? '') === 'ON_SITE')
                ? 'aguardando_oficina'
                : 'aguardando_guincho';
            $eventoCode = 'ORD-PAY-001';
            $guinchoHibridoId = (int)($pedido['guincho_id'] ?? 0);
            $downgradeHibrido = false;

            if ($statusOrigem === 'aguardando_pagamento_reboque_hibrido') {
                $hibridoAindaValido = $guinchoHibridoId > 0 && self::guinchoAindaValidoParaHibrido($guinchoHibridoId);
                if ($hibridoAindaValido) {
                    $statusNovo = 'preparacao_veiculo';
                    $eventoCode = 'ORD-PAY-HIB-001';
                } else {
                    $downgradeHibrido = true;
                    $eventoCode = 'ORD-PAY-HIB-DOWNGRADE-001';
                    Logger::log(Logger::LEVEL_WARN, 'PedidoTransitionService', 'approvePayment', 'conversao',
                        "Downgrade de h�brido para fila comum - pedido #{$pedidoId}: prestador #{$guinchoHibridoId} n�o est� mais apto (suspenso/capacidade revogada) no momento do pagamento.",
                        ['pedido_id' => $pedidoId, 'guincho_id' => $guinchoHibridoId]);
                }
            }

            if ($downgradeHibrido) {
                // Mesmo tratamento do caminho n�o-h�brido normal: libera o
                // prestador anterior, zera o v�nculo, vira TOWING de verdade
                // (Etapa 4 passa a filtrar por capacidade de reboque, n�o
                // pela capacidade do servi�o original) e entra na fila com
                // prazo/raio novos.
                $pdo->prepare("
                    UPDATE pedidos
                       SET status = ?,
                           attendance_mode = 'TOWING',
                           guincho_id = NULL,
                           expiracao_aceite = " . self::dateAddMinutesExpression($expMin) . ",
                           raio_atual_km = ?
                     WHERE id = ?
                ")->execute([
                    $statusNovo,
                    $raioInicial,
                    $pedidoId,
                ]);
                if ($guinchoHibridoId > 0) {
                    $pdo->prepare('UPDATE guinchos SET disponivel = 1 WHERE id = ?')->execute([$guinchoHibridoId]);
                }
            } elseif ($statusNovo === 'preparacao_veiculo') {
                // H�brido continua v�lido: s� muda o status - guincho_id,
                // disponibilidade e attendance_mode (HYBRID, j� gravado por
                // ConversionService) permanecem intactos.
                $pdo->prepare('UPDATE pedidos SET status = ? WHERE id = ?')->execute([$statusNovo, $pedidoId]);
            } else {
                $pdo->prepare("
                    UPDATE pedidos
                       SET status = 'aguardando_guincho',
                           expiracao_aceite = " . self::dateAddMinutesExpression($expMin) . ",
                           raio_atual_km = ?
                     WHERE id = ?
                ")->execute([
                    $raioInicial,
                    $pedidoId,
                ]);
            }

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pagamento_aprovado',
                $statusOrigem, $statusNovo,
                'system', $actorId, null,
                ['id_externo' => $idExterno, 'pagamento_id' => (int)$pag['id'], 'downgrade_hibrido' => $downgradeHibrido]
            );

            $pdo->commit();

            if (false) {
                try {
                    // O repasse � sempre recalculado sobre o valor efetivamente
                    // pago pelo cliente (incluindo dist�ncia e adicional noturno).
                    $repasseEspecialista = round((float)$total * 0.75, 2);
                    $taxaEspecialista = round((float)$total - $repasseEspecialista, 2);
                    IncidenteFinanceiroService::registrar($incidenteCriado, 'cobranca_cliente', 'pagamento', (int)$pag['id'], $total);
                    IncidenteFinanceiroService::registrar($incidenteCriado, 'taxa_plataforma', 'pagamento', (int)$pag['id'], $taxaEspecialista);
                    $atendimentoId = EspecialistaDispatchService::disparar($incidenteCriado, $codigoServicoEspecialista, $repasseEspecialista, $total, $taxaEspecialista);
                    if ($atendimentoId) {
                        IncidenteFinanceiroService::registrar($incidenteCriado, 'repasse_especialista', 'atendimento_especialista', $atendimentoId, $repasseEspecialista, 'pendente');
                    } else {
                        error_log('[EspecialistaDispatch] falha p�s-pagamento: retorno nulo; pedido_id=' . $pedidoId . ' incidente_id=' . $incidenteCriado . ' service_code=' . $codigoServicoEspecialista);
                    }
                } catch (Throwable $dispatchError) {
                    error_log('[EspecialistaDispatch] falha p�s-pagamento: exce��o; pedido_id=' . $pedidoId . ' incidente_id=' . $incidenteCriado . ' service_code=' . $codigoServicoEspecialista . ' erro=' . $dispatchError->getMessage());
                }
            }

            AuditTrailService::evento('pagamento_aprovado_pedido', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'system',
                'actor_id' => $actorId,
                'event_code' => $eventoCode,
                'status_anterior' => $pedido['status'],
                'status_novo' => $statusNovo,
                'id_externo' => $idExterno,
                'pagamento_id' => (int)$pag['id'],
                'downgrade_hibrido' => $downgradeHibrido,
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => $statusNovo,
                'pagamento_id' => (int)$pag['id'],
                'downgrade_hibrido' => $downgradeHibrido,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'approve_payment', $e, [
                'pedido_id' => $pedidoId,
                'id_externo' => $idExterno,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao aprovar o pagamento do pedido.');
        }
    }

    public static function acceptByGuincho(int $pedidoId, int $guinchoId, int $actorId, ?string $idempotencyKey = null): PedidoTransitionResult
    {
        return self::assignInternal($pedidoId, $guinchoId, 'guincho', $actorId, $idempotencyKey);
    }

    public static function assignByAdmin(int $pedidoId, int $guinchoId, int $actorId, ?string $idempotencyKey = null): PedidoTransitionResult
    {
        return self::assignInternal($pedidoId, $guinchoId, 'admin', $actorId, $idempotencyKey);
    }

    public static function transition(PedidoTransitionRequest $request): PedidoTransitionResult
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$request->pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }

            // Pacote L1.3 - noop expl�cito: pedir a mesma transi��o (origem === destino)
            // n�o � um bloqueador nem um "sucesso" que deve gerar novo evento de
            // auditoria com status_anterior === status_novo. Curto-circuita aqui,
            // antes de canTransition() (que continua aceitando from===to por
            // compatibilidade com outros chamadores).
            if ((string)$pedido['status'] === $request->targetStatus) {
                $pdo->rollBack();
                return PedidoTransitionResult::success($pedido, [
                    'status_anterior' => $pedido['status'],
                    'status_novo' => $request->targetStatus,
                    'noop' => true,
                ]);
            }

            if (!PedidoStateMachine::canTransition((string)$pedido['status'], $request->targetStatus, $pedido['attendance_mode'] ?? null)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Transi��o inv�lida para o status atual.', [
                    'status_atual' => $pedido['status'],
                    'status_destino' => $request->targetStatus,
                ]);
            }

            if (!self::authorizeTransition($pedido, $request)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Acesso negado para esta transi��o.');
            }

            $validation = self::validatePreconditions($pedido, $request);
            if ($validation !== null) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure($validation);
            }

            // Etapa 7 - convers�o de socorro local para reboque quando o
            // prestador atual N�O � h�brido: o pedido volta para
            // aguardando_guincho para uma NOVA disputa de matching, ent�o
            // precisa se desvincular do prestador anterior (que j� entregou
            // a parte dele e fica livre para outros chamados) e ganhar um
            // novo prazo de aceite - sen�o some da fila (expiracao_aceite
            // do primeiro aceite j� passou).
            $liberarGuinchoAnterior = $request->targetStatus === 'aguardando_guincho'
                && !empty($request->context['liberar_guincho_anterior'])
                && !empty($pedido['guincho_id']);

            $params = [$request->targetStatus];
            $sql = "UPDATE pedidos SET status = ?";

            if ($request->targetStatus === 'recusado_solicitando_reboque') {
                $sql .= ", attendance_mode = 'TOWING'";
            }
            if ($request->targetStatus === 'em_reboque' && !empty($request->context['foto_plataforma'])) {
                $sql .= ", foto_plataforma = ?";
                $params[] = (string)$request->context['foto_plataforma'];
            }
            if ($request->targetStatus === 'concluido' && !empty($request->context['foto_destino'])) {
                $sql .= ", foto_destino = ?";
                $params[] = (string)$request->context['foto_destino'];
            }
            if ($request->targetStatus === 'concluido' && ComissaoService::schemaDisponivel($pdo)) {
                $comissao = ComissaoService::persistirParaPedido($pdo, $pedido);
                Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'comissao', 'COMISSAO-OK: comiss�o persistida na conclus�o.', [
                    'pedido_id' => $request->pedidoId, 'tipo' => $comissao['tipo'], 'valor' => $comissao['valor'],
                ]);
            }
            if ($liberarGuinchoAnterior) {
                // attendance_mode -> TOWING � intencional aqui, n�o s� cosm�tico:
                // a partir deste ponto o pedido � literalmente um reboque (a fase
                // de socorro local j� terminou). Sem isso, o filtro de capacidade
                // da Etapa 4 continuaria exigindo aprova��o para o SERVI�O
                // ORIGINAL (ex.: partida auxiliar) em vez de capacidade de reboque
                // - nenhum guincho comum apareceria para aceitar. A valida��o da
                // transi��o em si j� rodou (canTransition, acima) usando o
                // attendance_mode ANTIGO, o que � correto: 'conversao_aprovada_cliente
                // ? aguardando_guincho' � uma transi��o v�lida do OnSiteFlowDefinition;
                // dali em diante (a_caminho/no_local/em_reboque/concluido) o
                // TowingFlowDefinition assume, e s�o exatamente os mesmos passos
                // do reboque comum.
                $sql .= ", guincho_id = NULL, expiracao_aceite = " . self::dateAddMinutesExpression(30) . ", attendance_mode = 'TOWING'";
            }
            $sql .= " WHERE id = ?";
            $params[] = $request->pedidoId;
            $pdo->prepare($sql)->execute($params);

            if ($liberarGuinchoAnterior) {
                $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([(int)$pedido['guincho_id']]);
            }

            if ($request->targetStatus === 'concluido' && !empty($pedido['guincho_id'])) {
                $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([(int)$pedido['guincho_id']]);
            }

            PedidoEventoService::registrar(
                $pdo, $request->pedidoId, 'pedido_transicao',
                (string)$pedido['status'], $request->targetStatus,
                $request->actorType, (int)$request->actorId,
                $request->context['motivo'] ?? null,
                ['liberou_guincho' => $liberarGuinchoAnterior]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_transicao', __CLASS__, __FUNCTION__, [
                'pedido_id' => $request->pedidoId,
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
                'event_code' => 'ORD-TRN-004',
                'status_anterior' => $pedido['status'],
                'status_novo' => $request->targetStatus,
            ]);

            // Monetiza��o de oficinas: o pedido j� foi commitado acima. Falhas
            // na indica��o nunca podem reverter/bloquear a conclus�o do reboque.
            if ($request->targetStatus === 'concluido') {
                try {
                    require_once __DIR__ . '/../IndicacaoOficinaService.php';
                    IndicacaoOficinaService::processarEntrega((int)$request->pedidoId);
                } catch (Throwable $indicacaoError) {
                    Logger::exception(__CLASS__, __FUNCTION__, 'indicacao_oficina_pos_commit', $indicacaoError, ['pedido_id' => $request->pedidoId]);
                }
            }
            return PedidoTransitionResult::success(Pedido::buscarPorId($request->pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => $request->targetStatus,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'transition', $e, [
                'pedido_id' => $request->pedidoId,
                'status_destino' => $request->targetStatus,
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao atualizar o pedido.');
        }
    }

    public static function cancelByAdmin(int $pedidoId, int $actorId, string $justificativa): PedidoTransitionResult
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if (in_array((string)$pedido['status'], ['cancelado', 'concluido'], true)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido j� encerrado.');
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET status = 'cancelado',
                       cancelado_por = 'admin',
                       motivo_cancelamento = ?,
                       taxa_cancelamento = 0.00,
                       cancelado_em = NOW()
                 WHERE id = ?
            ")->execute([mb_substr($justificativa, 0, 255), $pedidoId]);

            if (!empty($pedido['guincho_id'])) {
                $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([(int)$pedido['guincho_id']]);
            }

            self::registrarCancelamentoAuditado($pdo, $pedidoId, 'admin', $actorId, $justificativa, (string)$pedido['status'], 0.0);

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_cancelado_admin',
                (string)$pedido['status'], 'cancelado',
                'admin', $actorId, mb_substr($justificativa, 0, 255)
            );

            $pdo->commit();

            // �COBERTURA-RAIO-01 (06/08/2026): mesmo gap corrigido em
            // CancelamentoService::cancelarPorCliente() - este m�todo
            // cancela pedido em QUALQUER status n�o-terminal (s� bloqueia
            // cancelado/concluido acima), incluindo em_execucao_servico/
            // autorizacao_servico_pendente com or�amento j� aprovado e
            // estoque j� baixado. Chamado por AdminController::
            // cancelarPedido() (a��o manual do admin) e por DemandaService
            // (resolu��o de reclama��o/reembolso) - os dois caminhos
            // administrativos que mais provavelmente cancelam um pedido j�
            // em execu��o. Fora da transa��o de cancelamento, mesmo padr�o
            // do resto do m�todo (guincho liberado logo acima tamb�m �
            // fora da transa��o principal em esp�rito, ainda que antes do
            // commit aqui).
            require_once __DIR__ . '/../Estoque/EstoqueService.php';
            EstoqueService::estornarEstoqueDeOrcamentoAprovado($pedidoId);

            AuditTrailService::evento('pedido_cancelado_admin', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'admin',
                'actor_id' => $actorId,
                'event_code' => 'ORD-CAN-ADM-001',
                'status_anterior' => $pedido['status'],
                'justificativa' => $justificativa,
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => 'cancelado',
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'cancel_admin', $e, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'admin',
                'actor_id' => $actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao cancelar o pedido.');
        }
    }

    public static function cancelByCliente(int $pedidoId, int $actorId, string $justificativa, float $taxa = 0.0): PedidoTransitionResult
    {
        return self::cancelInternal($pedidoId, 'cliente', $actorId, $justificativa, $taxa);
    }

    /**
     * Pacote L1.6 - $penalidadeReputacao, quando informado, � debitado da
     * reputa��o do guincho na MESMA transa��o do reenfileiramento (at�mico),
     * em vez de uma segunda opera��o separada ap�s o commit.
     */
    public static function requeueByGuincho(int $pedidoId, int $guinchoId, int $actorId, string $justificativa, int $expMin, float $penalidadeReputacao = 0.0): PedidoTransitionResult
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmtPedido = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmtPedido->execute([$pedidoId]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if ((int)($pedido['guincho_id'] ?? 0) !== $guinchoId) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o pertence a este guincho.');
            }
            if ((string)$pedido['status'] !== 'a_caminho') {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Somente atendimentos em a_caminho podem voltar para a fila.');
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET status = 'aguardando_guincho',
                       guincho_id = NULL,
                       motivo_cancelamento = ?,
                       expiracao_aceite = ?
                 WHERE id = ?
            ")->execute([
                mb_substr('[guincho] ' . $justificativa, 0, 255),
                date('Y-m-d H:i:s', strtotime("+{$expMin} minutes")),
                $pedidoId,
            ]);
            $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([$guinchoId]);

            if ($penalidadeReputacao > 0) {
                $pdo->prepare("
                    UPDATE guinchos
                       SET total_cancelamentos = total_cancelamentos + 1,
                           reputacao = GREATEST(0, reputacao - ?)
                     WHERE id = ?
                ")->execute([$penalidadeReputacao, $guinchoId]);
            }

            self::registrarCancelamentoAuditado($pdo, $pedidoId, 'guincho', $actorId, $justificativa, (string)$pedido['status'], $penalidadeReputacao);

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_reenfileirado_guincho',
                (string)$pedido['status'], 'aguardando_guincho',
                'guincho', $actorId, mb_substr($justificativa, 0, 255),
                ['guincho_id' => $guinchoId, 'penalidade_reputacao' => $penalidadeReputacao]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_reenfileirado_guincho', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'guincho',
                'actor_id' => $actorId,
                'guincho_id' => $guinchoId,
                'event_code' => 'ORD-REQ-001',
                'status_anterior' => $pedido['status'],
                'status_novo' => 'aguardando_guincho',
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => 'aguardando_guincho',
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'requeue_guincho', $e, [
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'actor_id' => $actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao devolver o pedido para a fila.');
        }
    }

    /**
     * Salvaguarda para falha de GPS do cliente/guincho ou do pr�prio servidor
     * durante o atendimento (migration_conclusao_manual_v1.sql). A m�quina de
     * estados normal (validatePreconditions() acima) exige geofence + evid�ncia
     * atrelada a um ponto GPS v�lido para no_local/em_reboque/concluido - sem
     * NENHUM bypass, nem para actorType 'admin' (transition() aplica as mesmas
     * regras pra todo mundo). Isso � correto por padr�o, mas deixa o pedido
     * preso para sempre se o rastreamento simplesmente parar de funcionar.
     *
     * Esta rota N�O reusa transition()/validatePreconditions(): ela documenta
     * explicitamente que est� abrindo uma exce��o operacional, exige
     * justificativa m�nima, comprovantes manuais (fotos) para cada fase ainda
     * n�o evidenciada, e - ponto central, visto que "conclus�o manual sem
     * verifica��o" � um vetor cl�ssico de fraude em apps de entrega/reboque -
     * NUNCA fecha o ciclo de auditoria sozinha: o pedido sai marcado com
     * revisao_manual_status = 'pendente' e some para ser revisado.
     *
     * @param array<int, array{tipo:string, file: array, lat?: ?float, lng?: ?float}> $comprovantes
     */
    public static function concludeManuallyByAdmin(
        int $pedidoId,
        int $adminId,
        string $justificativa,
        array $comprovantes
    ): PedidoTransitionResult {
        DebugMode::trace('PedidoTransitionService', 'concludeManuallyByAdmin', 'pedido', 'iniciando conclus�o manual', [
            'pedido_id' => $pedidoId,
            'admin_id' => $adminId,
            'qtd_comprovantes' => count($comprovantes),
        ]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                DebugMode::trace('PedidoTransitionService', 'concludeManuallyByAdmin', 'pedido', 'rejeitado: pedido n�o encontrado', ['pedido_id' => $pedidoId]);
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if (!in_array((string)$pedido['status'], ['a_caminho', 'no_local', 'em_reboque'], true)) {
                $pdo->rollBack();
                DebugMode::trace('PedidoTransitionService', 'concludeManuallyByAdmin', 'pedido', 'rejeitado: status incompat�vel', [
                    'pedido_id' => $pedidoId,
                    'status_atual' => $pedido['status'],
                ]);
                return PedidoTransitionResult::failure('Conclus�o manual s� � permitida para atendimentos em andamento (a caminho, no local ou em reboque).', [
                    'status_atual' => $pedido['status'],
                ]);
            }

            $cfg = Configuracao::getAll();
            $minChars = (int)($cfg['conclusao_manual_justificativa_min_chars'] ?? 20);
            $justificativa = trim($justificativa);
            if (mb_strlen($justificativa) < $minChars) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure("Justificativa precisa ter pelo menos {$minChars} caracteres - explique o que falhou (GPS, app, servidor) e como o comprovante foi obtido.");
            }

            // Quais fases j� t�m evid�ncia normal (GPS) aceita? S� exige
            // comprovante manual para o que realmente falta.
            $jaTemColeta = self::possuiEvidenciaAceita($pdo, $pedidoId, 'coleta');
            $jaTemEntrega = self::possuiEvidenciaAceita($pdo, $pedidoId, 'entrega');

            $porTipo = [];
            foreach ($comprovantes as $c) {
                $tipo = (string)($c['tipo'] ?? '');
                if (!in_array($tipo, ['coleta', 'entrega'], true)) {
                    continue;
                }
                $porTipo[$tipo] = $c;
            }

            if (!$jaTemColeta && !isset($porTipo['coleta'])) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Comprovante manual de coleta � obrigat�rio (o pedido ainda n�o tem evid�ncia de coleta aceita via GPS).');
            }
            if (!$jaTemEntrega && !isset($porTipo['entrega'])) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Comprovante manual de entrega � obrigat�rio (o pedido ainda n�o tem evid�ncia de entrega aceita via GPS).');
            }

            $salvos = [];
            foreach ($porTipo as $tipo => $c) {
                try {
                    $salvos[] = self::salvarComprovanteManual($pdo, $pedidoId, $adminId, $tipo, $justificativa, $c);
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    Logger::exception(__CLASS__, __FUNCTION__, 'salvar_comprovante_manual', $e, [
                        'pedido_id' => $pedidoId, 'tipo' => $tipo,
                    ]);
                    return PedidoTransitionResult::failure('Falha ao salvar comprovante de ' . $tipo . ': ' . $e->getMessage());
                }
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET status = 'concluido',
                       concluido_manualmente = 1,
                       revisao_manual_status = 'pendente',
                       concluido_manual_admin_id = ?,
                       concluido_manual_justificativa = ?,
                       concluido_manual_em = NOW()
                 WHERE id = ?
            ")->execute([$adminId, mb_substr($justificativa, 0, 1000), $pedidoId]);

            if (!empty($pedido['guincho_id'])) {
                $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([(int)$pedido['guincho_id']]);
            }

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_concluido_manual',
                (string)$pedido['status'], 'concluido',
                'admin', $adminId, mb_substr($justificativa, 0, 255),
                ['comprovantes' => $salvos, 'revisao_manual_status' => 'pendente']
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_concluido_manual', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'event_code' => 'ORD-MAN-001',
                'status_anterior' => $pedido['status'],
                'status_novo' => 'concluido',
                'justificativa' => $justificativa,
                'comprovantes' => $salvos,
                'revisao_manual_status' => 'pendente',
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => 'concluido',
                'concluido_manualmente' => true,
                'revisao_manual_status' => 'pendente',
                'comprovantes' => $salvos,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'conclude_manually', $e, [
                'pedido_id' => $pedidoId,
                'admin_id' => $adminId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao concluir manualmente o pedido.');
        }
    }

    /**
     * Marca a revis�o de auditoria de uma conclus�o manual como confirmada ou
     * rejeitada. N�o desfaz a conclus�o (o pedido j� est� 'concluido' e pode
     * ter gerado obriga��o financeira) - serve para fechar o ciclo de
     * governan�a: algu�m verificou os comprovantes depois do fato e registrou
     * o veredito, ficando dispon�vel para auditoria futura.
     */
    public static function revisarConclusaoManual(int $pedidoId, int $adminId, string $veredito, string $nota = ''): PedidoTransitionResult
    {
        if (!in_array($veredito, ['confirmada', 'rejeitada'], true)) {
            return PedidoTransitionResult::failure('Veredito inv�lido.');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if (!(int)($pedido['concluido_manualmente'] ?? 0)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Este pedido n�o foi conclu�do manualmente.');
            }
            if ((string)($pedido['revisao_manual_status'] ?? '') !== 'pendente') {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Esta conclus�o manual j� foi revisada.', [
                    'revisao_manual_status' => $pedido['revisao_manual_status'],
                ]);
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET revisao_manual_status = ?,
                       revisao_manual_admin_id = ?,
                       revisao_manual_em = NOW(),
                       revisao_manual_nota = ?
                 WHERE id = ?
            ")->execute([$veredito, $adminId, mb_substr($nota, 0, 500), $pedidoId]);

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_revisao_manual',
                null, null,
                'admin', $adminId, mb_substr($nota, 0, 255),
                ['veredito' => $veredito]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_revisao_manual', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'event_code' => 'ORD-MAN-002',
                'revisao_manual_status' => $veredito,
                'nota' => $nota,
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'revisao_manual_status' => $veredito,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'revisar_manual', $e, [
                'pedido_id' => $pedidoId,
                'admin_id' => $adminId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao registrar revis�o manual.');
        }
    }

    private static function possuiEvidenciaAceita(PDO $pdo, int $pedidoId, string $tipo): bool
    {
        $stmt = $pdo->prepare("SELECT id FROM pedido_evidencias WHERE pedido_id = ? AND tipo = ? AND status = 'accepted' LIMIT 1");
        $stmt->execute([$pedidoId, $tipo]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{tipo:string, file: array, lat?: ?float, lng?: ?float} $comprovante
     * @return array{id:int, tipo:string, stored_name:string, sha256:string}
     */
    private static function salvarComprovanteManual(PDO $pdo, int $pedidoId, int $adminId, string $tipo, string $justificativa, array $comprovante): array
    {
        $file = $comprovante['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Arquivo de comprovante ausente ou inv�lido.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('Arquivo acima do limite de 5MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Tipo MIME inv�lido para comprovante (use JPEG ou PNG).');
        }

        $destDir = EvidenceService::privateStorageDir();
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0770, true);
        }

        $storedName = sprintf(
            'manual_%s_%d_admin%d_%s.%s',
            $tipo,
            $pedidoId,
            $adminId,
            bin2hex(random_bytes(8)),
            $allowed[$mime]
        );
        $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;
        $moved = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $destPath)
            : rename((string)$file['tmp_name'], $destPath); // Playwright/CLI podem simular upload sem is_uploaded_file()==true
        if (!$moved) {
            throw new RuntimeException('Falha ao mover arquivo de comprovante manual.');
        }

        $sha256 = (string)hash_file('sha256', $destPath);

        $stmt = $pdo->prepare(
            'INSERT INTO pedido_evidencias_manuais
                (pedido_id, admin_id, tipo, justificativa, latitude_informada, longitude_informada,
                 original_name, stored_name, mime_type, size_bytes, sha256, ip, user_agent, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $pedidoId,
            $adminId,
            $tipo,
            mb_substr($justificativa, 0, 1000),
            isset($comprovante['lat']) ? (float)$comprovante['lat'] : null,
            isset($comprovante['lng']) ? (float)$comprovante['lng'] : null,
            (string)($file['name'] ?? $storedName),
            $storedName,
            $mime,
            (int)$file['size'],
            $sha256,
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        return [
            'id' => (int)$pdo->lastInsertId(),
            'tipo' => $tipo,
            'stored_name' => $storedName,
            'sha256' => $sha256,
        ];
    }

    private static function assignInternal(int $pedidoId, int $guinchoId, string $actorType, int $actorId, ?string $idempotencyKey = null): PedidoTransitionResult
    {
        $pdo = getPDO();

        // Pacote L1.3 - idempot�ncia de aceite concorrente. A corrida "dois
        // guinchos aceitam o mesmo pedido ao mesmo tempo" j� era mitigada por
        // SELECT ... FOR UPDATE (o segundo a obter o lock v� status != 'aguardando_guincho'
        // e falha normalmente). O que faltava era prote��o contra RETRY do mesmo
        // request (ex: timeout de rede e o app reenvia o aceite): sem uma chave de
        // idempot�ncia, o segundo envio do mesmo guincho podia falhar com "pedido
        // n�o est� aguardando guincho" mesmo tendo sido ele mesmo que aceitou.
        if ($idempotencyKey !== null) {
            $cached = self::fetchIdempotentResult($pdo, $idempotencyKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $pdo->beginTransaction();
            $stmtPedido = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmtPedido->execute([$pedidoId]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if ((string)$pedido['status'] !== 'aguardando_guincho') {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o est� aguardando guincho.');
            }

            $stmtGuincho = $pdo->prepare("SELECT * FROM guinchos WHERE id = ?" . self::lockClause($pdo));
            $stmtGuincho->execute([$guinchoId]);
            $guincho = $stmtGuincho->fetch(PDO::FETCH_ASSOC);
            if (!$guincho || !(bool)$guincho['aprovado'] || !(bool)$guincho['disponivel']) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Guincho indispon�vel ou n�o aprovado.');
            }

            require_once __DIR__ . '/../Finance/BloqueioFinanceiroService.php';
            if (BloqueioFinanceiroService::guinchoEstaBloqueado($guinchoId)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('N�o foi poss�vel aceitar. Tente novamente mais tarde.');
            }

            // Etapa 4 (matching por capacidade) - defesa em profundidade: ...

            // Etapa 4 (matching por capacidade) - defesa em profundidade: o
            // filtro de listagem (GuinchoController::montarOfertasDisponiveis,
            // SseController::pedidosDisponiveis) j� esconde pedidos de
            // servi�os novos (ON_SITE/HYBRID) de guinchos sem capacidade
            // aprovada, mas o aceite em si n�o pode confiar s� nisso - algu�m
            // podia enviar um POST direto pra /guincho/aceitar com o ID de um
            // pedido que nunca deveria ter visto. Reboque (TOWING) continua
            // sem essa exig�ncia, igual ao comportamento j� em produ��o.
            $attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');
            if ($attendanceMode === 'TOWING') {
                // Reboque exige reboque_aprovado - especialista n�o aceita reboque.
                if ((int)($guincho['reboque_aprovado'] ?? 1) !== 1) {
                    $pdo->rollBack();
                    return PedidoTransitionResult::failure('Prestador n�o est� aprovado para reboque.');
                }
            } else {
                $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);
                if ($serviceTypeId <= 0 || !ProviderCapability::possuiCapacidadeAprovada($guinchoId, $serviceTypeId)) {
                    $pdo->rollBack();
                    return PedidoTransitionResult::failure('Guincho n�o possui capacidade aprovada para este tipo de servi�o.');
                }
            }

            // Etapa 15 (compatibilidade prestador � ve�culo) - revalida��o
            // DENTRO da transa��o. A fila pode estar velha (capacidade suspensa
            // depois de o pedido aparecer; POST direto burlando o filtro). S�
            // ELIGIBLE passa; REQUIRES_CONFIRMATION n�o pode ser aceito direto
            // (o prestador precisa revisar os dados antes). Fallback conservador
            // no servi�o garante que reboque sem config configurada = ELIGIBLE,
            // ent�o nada muda pro fluxo atual em produ��o.
            $serviceTypeIdCmp = (int)($pedido['service_type_id'] ?? 0);
            if ($serviceTypeIdCmp > 0) {
                $compat = ProviderVehicleCompatibilityService::evaluate(new CompatibilityRequest(
                    $pedidoId,
                    $guinchoId,
                    $serviceTypeIdCmp,
                    CompatibilityRequest::OP_ORDER_ACCEPTANCE
                ));
                if (!$compat->isEligible()) {
                    $pdo->rollBack();
                    $codigo = $compat->getPrimaryReasonCode();
                    $msg = $compat->getStatus() === CompatibilityResult::REQUIRES_CONFIRMATION
                        ? 'Compatibilidade precisa ser confirmada antes do aceite (' . $codigo . ').'
                        : 'Prestador incompat�vel com o ve�culo/ocorr�ncia (' . $codigo . ').';
                    return PedidoTransitionResult::failure($msg);
                }
            }

            // �A1 - dist�ncia guincho?origem no momento do aceite, base correta
            // pro distance_ratio do cancelamento (CancellationCalculationService),
            // que antes normalizava contra a dist�ncia origem?destino do pedido.
            $distanciaGuinchoOrigemKm = null;
            if (isset($guincho['lat_atual'], $guincho['lng_atual'], $pedido['lat_origem'], $pedido['lng_origem'])
                && $guincho['lat_atual'] !== null && $guincho['lng_atual'] !== null) {
                $distanciaGuinchoOrigemKm = round(GeoService::haversine(
                    (float)$guincho['lat_atual'],
                    (float)$guincho['lng_atual'],
                    (float)$pedido['lat_origem'],
                    (float)$pedido['lng_origem']
                ), 2);
            }

            $pdo->prepare("UPDATE pedidos SET guincho_id = ?, status = 'a_caminho', distancia_guincho_origem_km = ? WHERE id = ?")
                ->execute([$guinchoId, $distanciaGuinchoOrigemKm, $pedidoId]);
            $pdo->prepare("UPDATE guinchos SET disponivel = 0 WHERE id = ?")
                ->execute([$guinchoId]);

            if ($idempotencyKey !== null) {
                try {
                    $pdo->prepare("
                        INSERT INTO pedido_idempotency
                            (idempotency_key, pedido_id, operation, actor_type, actor_id, success, response_json)
                        VALUES (?, ?, 'assign', ?, ?, 1, ?)
                    ")->execute([
                        $idempotencyKey,
                        $pedidoId,
                        $actorType,
                        $actorId,
                        json_encode([
                            'status_anterior' => $pedido['status'],
                            'status_novo' => 'a_caminho',
                            'guincho_id' => $guinchoId,
                        ]),
                    ]);
                } catch (PDOException $dup) {
                    // Duplicate key: outra requisi��o concorrente com a MESMA
                    // idempotency_key j� est� sendo processada/foi processada.
                    $pdo->rollBack();
                    return PedidoTransitionResult::failure('Requisi��o j� em processamento ou conclu�da (idempotency_key duplicada).');
                }
            }

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_aceito',
                (string)$pedido['status'], 'a_caminho',
                $actorType, $actorId, null,
                ['guincho_id' => $guinchoId, 'distancia_guincho_origem_km' => $distanciaGuinchoOrigemKm]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_aceito', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'guincho_id' => $guinchoId,
                'event_code' => 'ORD-ACC-001',
                'status_anterior' => $pedido['status'],
                'status_novo' => 'a_caminho',
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => 'a_caminho',
                'guincho_id' => $guinchoId,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'assign', $e, [
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao atribuir guincho.');
        }
    }

    /**
     * Pacote L1.3 - busca um resultado j� registrado para esta idempotency_key.
     * Retorna null se a chave nunca foi usada (segue fluxo normal).
     */
    private static function fetchIdempotentResult(PDO $pdo, string $idempotencyKey): ?PedidoTransitionResult
    {
        $stmt = $pdo->prepare("SELECT pedido_id, success, response_json FROM pedido_idempotency WHERE idempotency_key = ? LIMIT 1");
        $stmt->execute([$idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $decoded = json_decode((string)$row['response_json'], true) ?: [];
        if (!(int)$row['success']) {
            return PedidoTransitionResult::failure('Requisi��o anterior com esta idempotency_key falhou. Gere uma nova chave para tentar novamente.');
        }

        $pedido = Pedido::buscarPorId((int)$row['pedido_id']) ?? ['id' => (int)$row['pedido_id']];
        return PedidoTransitionResult::success($pedido, $decoded);
    }

    /**
     * Pacote L1.6 - grava em pedido_cancelamentos (tabela criada pela migration
     * migration_fix_cancelamento_polling.sql, que ficou �rf�: nenhum fluxo real
     * gravava nela). Best-effort: falha aqui n�o pode impedir o cancelamento,
     * mas roda DENTRO da mesma transa��o para ficar at�mica com o resto quando
     * a tabela existir e o insert funcionar.
     */
    private static function registrarCancelamentoAuditado(PDO $pdo, int $pedidoId, string $atorTipo, int $atorId, string $motivo, string $statusAnterior, float $penalidade): void
    {
        try {
            $pdo->prepare(
                'INSERT INTO pedido_cancelamentos (pedido_id, ator_tipo, ator_id, motivo, status_anterior, penalidade, ip, user_agent, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $pedidoId, $atorTipo, $atorId, mb_substr($motivo, 0, 1000), $statusAnterior, $penalidade,
                (string)($_SERVER['REMOTE_ADDR'] ?? ''), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'auditoria_cancelamento', $e, [
                'pedido_id' => $pedidoId, 'ator_tipo' => $atorTipo, 'ator_id' => $atorId, 'fase' => 'insert_best_effort',
            ]);
        }
    }

    private static function cancelInternal(int $pedidoId, string $actorType, int $actorId, string $justificativa, float $taxa): PedidoTransitionResult
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido n�o encontrado.');
            }
            if (in_array((string)$pedido['status'], ['cancelado', 'concluido'], true)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido j� encerrado.');
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET status = 'cancelado',
                       cancelado_por = ?,
                       motivo_cancelamento = ?,
                       taxa_cancelamento = ?,
                       cancelado_em = NOW()
                 WHERE id = ?
            ")->execute([
                $actorType,
                mb_substr($justificativa, 0, 255),
                $taxa,
                $pedidoId,
            ]);

            if (!empty($pedido['guincho_id'])) {
                $pdo->prepare("UPDATE guinchos SET disponivel = 1 WHERE id = ?")->execute([(int)$pedido['guincho_id']]);
            }

            self::registrarCancelamentoAuditado($pdo, $pedidoId, $actorType, $actorId, $justificativa, (string)$pedido['status'], $taxa);

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_cancelado',
                (string)$pedido['status'], 'cancelado',
                $actorType, $actorId, mb_substr($justificativa, 0, 255),
                ['taxa_cancelamento' => $taxa]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_cancelado', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'event_code' => 'ORD-CAN-001',
                'status_anterior' => $pedido['status'],
                'status_novo' => 'cancelado',
                'taxa_cancelamento' => $taxa,
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'],
                'status_novo' => 'cancelado',
                'taxa_cancelamento' => $taxa,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'cancel', $e, [
                'pedido_id' => $pedidoId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao cancelar o pedido.');
        }
    }

    private static function authorizeTransition(array $pedido, PedidoTransitionRequest $request): bool
    {
        if ($request->actorType === 'system') {
            return true;
        }
        if ($request->actorType === 'admin') {
            return true;
        }
        if ($request->actorType === 'guincho') {
            return (int)($pedido['guincho_id'] ?? 0) === (int)($request->guinchoId ?? 0);
        }
        if ($request->actorType === 'cliente') {
            // Etapa 5/7 - os �NICOS casos em que o cliente dispara uma
            // transi��o diretamente: aprovar or�amento complementar ou
            // aprovar a convers�o de socorro local para reboque. Qualquer
            // outra combina��o fica bloqueada de prop�sito (cancelamento do
            // cliente continua passando por cancelByCliente(), n�o por
            // transition() gen�rico).
            if ((int)($pedido['cliente_id'] ?? 0) !== (int)$request->actorId) {
                return false;
            }
            if ((string)$pedido['status'] === 'autorizacao_servico_pendente' && in_array($request->targetStatus, ['decisao_reboque_pendente', 'saida_oficina_pendente', 'aguardando_pagamento_orcamento', 'em_execucao_servico', 'recusado_solicitando_reboque'], true)) {
                return true;
            }
            if ((string)$pedido['status'] === 'conversao_reboque_pendente' && $request->targetStatus === 'conversao_aprovada_cliente') {
                return true;
            }
            return false;
        }

        return false;
    }

    private static function validatePreconditions(array $pedido, PedidoTransitionRequest $request): ?string
    {
        // Pacote L1.3 - o ator 'system' (cron/webhook/servi�o interno) N�O � mais
        // isento de geofence/evid�ncia. O bypass anterior permitia que qualquer
        // c�digo interno levasse um pedido a no_local/em_reboque/concluido sem
        // prova nenhuma. As demais transi��es (ex: cancelamento, reenfileirar)
        // n�o passam por este m�todo, ent�o nenhum fluxo leg�timo de 'system'
        // depende deste bypass.
        $snapshot = ProofOfRoadService::getCurrentSnapshot((int)$pedido['id']);
        $lastValid = $snapshot['last_valid_point'] ?? null;
        // �A3 - raio �nico (PorThresholds), antes duplicado aqui e em
        // EvidenceService com o mesmo default mas leitura pr�pria de config.
        $radiusOrigem = PorThresholds::arrivalRadiusM();
        $radiusDestino = PorThresholds::destinationRadiusM();

        if ($request->targetStatus === 'no_local') {
            if (!$lastValid || !GeofenceService::isNearOrigin($pedido, (float)$lastValid['latitude'], (float)$lastValid['longitude'], $radiusOrigem)) {
                return 'Chegada bloqueada: guincho fora da geofence da origem.';
            }
        }

        if ($request->targetStatus === 'em_reboque') {
            if (!$lastValid || !GeofenceService::isNearOrigin($pedido, (float)$lastValid['latitude'], (float)$lastValid['longitude'], $radiusOrigem)) {
                return 'Coleta bloqueada: guincho fora da geofence da origem.';
            }
            $evidenceError = self::validateEvidence($pedido, $request, 'coleta');
            if ($evidenceError !== null) {
                return $evidenceError;
            }
        }

        if ($request->targetStatus === 'concluido') {
            if (!$lastValid || !GeofenceService::isNearDestination($pedido, (float)$lastValid['latitude'], (float)$lastValid['longitude'], $radiusDestino)) {
                return 'Conclus�o bloqueada: guincho fora da geofence do destino.';
            }
            $evidenceError = self::validateEvidence($pedido, $request, 'entrega');
            if ($evidenceError !== null) {
                return $evidenceError;
            }
        }

        return null;
    }

    /**
     * Pacote L1.3 - valida a evid�ncia por `evidence_id` persistido em
     * `pedido_evidencias`, n�o mais por nome de arquivo solto no contexto
     * da requisi��o. Isso garante que o arquivo referenciado passou pelo
     * fluxo real de upload (nonce, geofence, MIME, hash) em EvidenceService
     * e est� com status 'accepted' para este pedido/tipo espec�fico.
     *
     * Mant�m compatibilidade retroativa: se o contexto ainda trouxer apenas
     * 'foto_plataforma'/'foto_destino' (fluxo legado) e nenhum 'evidence_id',
     * cai no comportamento antigo - mas isso deve ser tratado como transit�rio
     * e migrado para sempre enviar evidence_id.
     */
    private static function validateEvidence(array $pedido, PedidoTransitionRequest $request, string $tipo): ?string
    {
        $evidenceId = $request->context['evidence_id'] ?? null;
        $legacyField = $tipo === 'coleta' ? 'foto_plataforma' : 'foto_destino';

        if ($evidenceId === null) {
            if (empty($request->context[$legacyField])) {
                return $tipo === 'coleta'
                    ? 'Foto de coleta com nonce v�lido � obrigat�ria.'
                    : 'Foto de entrega com nonce v�lido � obrigat�ria.';
            }
            // Fluxo legado sem evidence_id - aceito por compatibilidade, mas sem
            // a garantia forte de que o arquivo passou pelo pipeline de evid�ncia.
            return null;
        }

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT id, status, stored_name FROM pedido_evidencias
              WHERE id = ? AND pedido_id = ? AND tipo = ?
              LIMIT 1"
        );
        $stmt->execute([(int)$evidenceId, (int)$pedido['id'], $tipo]);
        $evidencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evidencia) {
            return 'Evid�ncia n�o encontrada para este pedido/etapa.';
        }
        if ((string)$evidencia['status'] !== 'accepted') {
            return 'Evid�ncia ainda n�o foi aceita (status: ' . $evidencia['status'] . ').';
        }

        return null;
    }

    /**
     * �HIBRIDO-COMPLEMENTAR-01 - mesma checagem de
     * ConversionService::guinchoValidoParaHibrido(), duplicada
     * intencionalmente aqui: o momento da REVALIDA��O (aprova��o do
     * pagamento) � conceitualmente diferente do momento da DECIS�O
     * (convers�o), mesmo usando o mesmo crit�rio de elegibilidade hoje -
     * evita acoplar os dois servi�os por uma depend�ncia cruzada s� por
     * causa de um helper que pode divergir no futuro (ex.: revalida��o mais
     * r�gida no momento do pagamento).
     */
    private static function guinchoAindaValidoParaHibrido(int $guinchoId): bool
    {
        $guincho = Guincho::buscarPorId($guinchoId);
        if (!$guincho || !(bool)($guincho['aprovado'] ?? false)) {
            return false;
        }
        if (array_key_exists('reboque_aprovado', $guincho) && !(bool)$guincho['reboque_aprovado']) {
            return false;
        }
        return ProviderCapability::possuiCapacidadeReboqueAprovada($guinchoId);
    }

    private static function lockClause(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private static function dateAddMinutesExpression(int $minutes): string
    {
        $minutes = max(1, $minutes);
        $pdo = getPDO();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return "datetime('now', '+" . $minutes . " minutes')";
        }

        return "DATE_ADD(NOW(), INTERVAL " . $minutes . " MINUTE)";
    }

    public static function requeueByOficina(int $pedidoId, int $oficinaId, int $actorId, string $justificativa, int $expMin, float $penalidadeReputacao = 0.0): PedidoTransitionResult
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $stmtPedido = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmtPedido->execute([$pedidoId]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido nao encontrado.');
            }
            if ((int)($pedido['oficina_id'] ?? 0) !== $oficinaId) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('Pedido nao pertence a esta oficina.');
            }
            if (!in_array((string)$pedido['status'], ['oficina_aceitou', 'oficina_a_caminho'], true)) {
                $pdo->rollBack();
                return PedidoTransitionResult::failure('So e possivel cancelar antes de chegar ao local.');
            }

            $attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');
            $statusFila = 'aguardando_guincho';
            if ($attendanceMode === 'ON_SITE' || $attendanceMode === 'HYBRID') {
                $statusFila = 'aguardando_oficina';
            }

            $pdo->prepare("
                UPDATE pedidos
                   SET status = ?, oficina_id = NULL, motivo_cancelamento = ?, expiracao_aceite = ?
                 WHERE id = ?
            ")->execute([
                $statusFila,
                mb_substr('[oficina] ' . $justificativa, 0, 255),
                date('Y-m-d H:i:s', strtotime("+{$expMin} minutes")),
                $pedidoId,
            ]);

            if ($penalidadeReputacao > 0) {
                try {
                    $pdo->prepare("
                        UPDATE oficinas
                           SET total_cancelamentos = total_cancelamentos + 1,
                               reputacao = GREATEST(0, reputacao - ?)
                         WHERE id = ?
                    ")->execute([$penalidadeReputacao, $oficinaId]);
                } catch (Throwable $eRep) {
                    Logger::exception(__CLASS__, __FUNCTION__, 'requeue_oficina_reputacao', $eRep, [
                        'pedido_id' => $pedidoId, 'oficina_id' => $oficinaId,
                    ]);
                }
            }

            self::registrarCancelamentoAuditado($pdo, $pedidoId, 'oficina', $actorId, $justificativa, (string)$pedido['status'], $penalidadeReputacao);

            PedidoEventoService::registrar(
                $pdo, $pedidoId, 'pedido_reenfileirado_oficina',
                (string)$pedido['status'], $statusFila,
                'oficina', $actorId, mb_substr($justificativa, 0, 255),
                ['oficina_id' => $oficinaId, 'penalidade_reputacao' => $penalidadeReputacao]
            );

            $pdo->commit();

            AuditTrailService::evento('pedido_reenfileirado_oficina', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId, 'actor_type' => 'oficina', 'actor_id' => $actorId,
                'oficina_id' => $oficinaId, 'event_code' => 'ORD-REQ-OFI-001',
                'status_anterior' => $pedido['status'], 'status_novo' => $statusFila,
            ]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? $pedido, [
                'status_anterior' => $pedido['status'], 'status_novo' => $statusFila,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Logger::exception(__CLASS__, __FUNCTION__, 'requeue_oficina', $e, [
                'pedido_id' => $pedidoId, 'oficina_id' => $oficinaId, 'actor_id' => $actorId,
            ]);
            return PedidoTransitionResult::failure('Erro interno ao devolver o pedido para a fila.');
        }
    }
    
        /**
     * �5.7 - extrai o m�todo de pagamento efetivo do payload do gateway.
     * Cobre MercadoPago (API v1 payments) e PagSeguro (Orders API + legado).
     * Devolve 'pix' | 'credito' | 'debito' | 'boleto' | 'desconhecido'.
     */
    private static function detectarMetodoPagamento(string $payload): string
    {
        if ($payload === '') {
            return 'desconhecido';
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return 'desconhecido';
        }

        // MercadoPago - payment_type_id
        if (isset($decoded['payment_type_id'])) {
            $tipo = strtolower((string)$decoded['payment_type_id']);
            return match ($tipo) {
                'pix'           => 'pix',
                'credit_card'   => 'credito',
                'debit_card'    => 'debito',
                'ticket'        => 'boleto',
                'account_money' => 'pix',   // saldo MP cai como PIX
                default         => 'desconhecido',
            };
        }

        // PagSeguro (Orders API) - paymentMethod.type
        $tipoPS = $decoded['paymentMethod']['type']
            ?? $decoded['charges'][0]['payment_method']['type']
            ?? null;
        if (is_string($tipoPS) && $tipoPS !== '') {
            return match (strtoupper($tipoPS)) {
                'PIX'         => 'pix',
                'CREDIT_CARD' => 'credito',
                'DEBIT_CARD'  => 'debito',
                'BOLETO'      => 'boleto',
                default       => 'desconhecido',
            };
        }

        return 'desconhecido';
    }

    /**
     * �5.7 - escolhe a reserva de gateway com base no m�todo efetivo.
     * Fallback: cr�dito � vista (pior caso comum).
     */
    private static function reservaGatewayPara(array $cfg, string $metodo): float
    {
        // A configura��o global continua sendo o contrato de compatibilidade
        // para ambientes legados e testes; taxas espec�ficas s� assumem quando
        // a global n�o foi definida.
        if (array_key_exists('reserva_gateway_percentual', $cfg)) {
            $raw = (float)$cfg['reserva_gateway_percentual'];
            return $raw > 1 ? $raw / 100 : $raw;
        }
        $chave = match ($metodo) {
            'pix'     => 'reserva_gateway_pix',
            'debito'  => 'reserva_gateway_debito',
            'credito' => 'reserva_gateway_credito',
            'boleto'  => 'reserva_gateway_boleto',
            default   => 'reserva_gateway_credito', // pior caso
        };
        $raw = isset($cfg[$chave]) ? (float)$cfg[$chave] : 0.045;
        return $raw > 1 ? $raw / 100 : $raw;
    }
}
