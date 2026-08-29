<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Conversion/ConversionService.php
// ROADMAP socorro automotivo — Etapa 7 (conversão de socorro local para reboque).
//
// Decide o que acontece depois que o cliente aprova a conversão
// (conversao_reboque_pendente → conversao_aprovada_cliente):
//   - prestador atual já tem capacidade de REBOQUE aprovada (híbrido) →
//     continua vinculado ao mesmo pedido, sem nova disputa de matching, mas
//     PRECISA pagar o complementar de reboque antes de preparacao_veiculo
//     (aguardando_pagamento_reboque_hibrido — ver §HIBRIDO-COMPLEMENTAR-01);
//   - senão → pedido volta para aguardando_pagamento com uma cobrança
//     COMPLEMENTAR real do reboque (ver §COBRANCA-REBOQUE-01 abaixo); só
//     depois de pago é que o matching por capacidade (Etapa 4) entra em jogo
//     a partir de aguardando_guincho.
//
// §COBRANCA-REBOQUE-01 (24/07/2026, correção de lacuna apontada pelo
// usuário): o valor pago pelo socorro no local (deslocamento + diagnóstico)
// NÃO cobre o reboque — são serviços com precificação diferente. Antes
// desta correção, decidirConversao() soltava o pedido de volta pra fila de
// reboque SEM NUNCA cobrar o cliente por isso e SEM NUNCA coletar o
// endereço de destino (o pedido de socorro no local corretamente não exige
// destino upfront — service_types.requires_destination=0 — mas alguém
// precisa perguntar "para onde rebocar" quando o reboque de fato se torna
// necessário). Esta classe: (1) exige que o chamador informe o destino já
// geocodificado (endereco/lat/lng) nesse momento — nos DOIS caminhos, desde
// §HIBRIDO-COMPLEMENTAR-01; (2) calcula o valor do reboque com a MESMA
// tarifa usada por qualquer pedido de reboque comum (zona de precificação
// ou TarifaService::calcularDetalhado como fallback); (3) grava esse valor
// como novo custo_estimado do pedido.
//
// §CREDITO-CONVERSAO-01 (26/07/2026): o abatimento no reboque complementar
// pelo valor já pago no socorro no local (credito_conversao_percentual/
// credito_conversao_maximo em Configuracao) é aplicado nos DOIS caminhos.
//
// §HIBRIDO-COMPLEMENTAR-01 (27/07/2026, correção de lacuna apontada pelo
// usuário): o caminho híbrido pulava direto pra preparacao_veiculo sem
// cobrar nada — mesmo bug de fundo do não-híbrido, nunca corrigido. Agora os
// dois caminhos compartilham EXATAMENTE o mesmo cálculo de destino/custo/
// crédito; o que muda entre eles é só o status de destino pós-cobrança
// (preparacao_veiculo direto vs. nova disputa de matching) — ver
// PedidoTransitionService::approvePayment() para o branch pós-pagamento e a
// revalidação de capacidade do prestador híbrido no momento da aprovação
// (pode ter perdido a aprovação/capacidade entre a decisão de conversão e o
// pagamento efetivo do complementar).
//
// Descoberta ao implementar §HIBRIDO-COMPLEMENTAR-01: `pagamentos` tem
// UNIQUE(pedido_id) e Pagamento::reiniciarTentativa() recusa (de propósito)
// reaproveitar a linha quando o status já é 'aprovado' — ou seja, a cobrança
// complementar (híbrida OU não-híbrida) na prática NUNCA conseguia nem ser
// criada: PagamentoController::mercadoPagoTransparente() chama
// Pagamento::criar() de novo quando o pedido volta pra aguardando_pagamento,
// e isso batia direto nessa guarda. Corrigido via
// Pagamento::arquivarParaCobrancaComplementar(), chamado logo abaixo — ver
// o método para o racional completo.

require_once __DIR__ . '/../Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionResult.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../../Models/Pagamento.php';
require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../../Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../GeoService.php';
require_once __DIR__ . '/../TarifaService.php';
require_once __DIR__ . '/../Logger.php';

final class ConversionService
{
    /**
     * Cliente decide se aceita a conversão para reboque.
     * Recusa: fica em conversao_reboque_pendente (nenhum cancelamento
     * automático — mesma política já adotada para orçamento recusado e
     * falha de pagamento: resolução manual via admin/Demanda).
     *
     * @param array{endereco?:string,lat?:float,lng?:float} $destino Obrigatório
     *   quando $aprovado=true (híbrido ou não — os dois caminhos cobram o
     *   complementar de reboque desde §HIBRIDO-COMPLEMENTAR-01). Ignorado
     *   na recusa.
     */
    public static function decidirConversao(int $pedidoId, int $clienteId, bool $aprovado, array $destino = []): PedidoTransitionResult
    {
        if (!$aprovado) {
            Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
                "Conversão para reboque recusada pelo cliente — pedido #{$pedidoId}",
                ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId]);
            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['conversao' => 'recusada']);
        }

        $step1 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'cliente', $clienteId, $pedidoId, 'conversao_aprovada_cliente'
        ));
        if (!$step1->ok) {
            return $step1;
        }

        // §CONCORRENCIA-01 (27/07/2026): transition() trata from===to como
        // noop bem-sucedido (compatibilidade retroativa) — o que significa
        // que uma chamada REPETIDA de decidirConversao() para um pedido que
        // já saiu de 'conversao_reboque_pendente' (retry de rede, duplo
        // clique do cliente, reenvio de request) chegaria até aqui com
        // $step1->ok=true sem ter feito nenhuma transição de verdade. Sem
        // este guard, o código abaixo arquivaria o pagamento (já arquivado
        // na primeira chamada) e aplicaria o crédito DE NOVO. Curto-circuita
        // aqui: devolve o estado atual sem reprocessar nada.
        if (!empty($step1->context['noop'])) {
            Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
                "Conversão já havia sido processada — pedido #{$pedidoId} não está mais em conversao_reboque_pendente (chamada duplicada ignorada).",
                ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId]);
            return PedidoTransitionResult::success($step1->pedido ?? [], ['conversao' => 'ja_processada']);
        }

        $pedido = $step1->pedido ?? [];
        $guinchoAtualId = (int)($pedido['guincho_id'] ?? 0);
        $ehHibrido = $guinchoAtualId > 0 && self::guinchoValidoParaHibrido($guinchoAtualId);

        $destinoLat = isset($destino['lat']) ? (float)$destino['lat'] : null;
        $destinoLng = isset($destino['lng']) ? (float)$destino['lng'] : null;
        $destinoEndereco = trim((string)($destino['endereco'] ?? ''));
        if ($destinoLat === null || $destinoLng === null || $destinoEndereco === '' || !GeoService::coordenadasValidas($destinoLat, $destinoLng)) {
            return PedidoTransitionResult::failure(
                'Informe o endereço para onde o veículo será rebocado antes de aprovar a conversão.'
            );
        }

        $distanciaKm = max(0.1, GeoService::haversine(
            (float)$pedido['lat_origem'],
            (float)$pedido['lng_origem'],
            $destinoLat,
            $destinoLng
        ));

        $veiculo = null;
        if (!empty($pedido['veiculo_id'])) {
            require_once __DIR__ . '/../../Models/Veiculo.php';
            $veiculo = Veiculo::buscarPorId((int)$pedido['veiculo_id']);
        }
        $categoria = $veiculo ? TarifaService::categoriaDeVeiculo($veiculo) : 'popular';

        // §DESLOCAMENTO-01/Etapa 13: mesma ordem de resolução usada em
        // ClienteController — tenta a zona de precificação primeiro (ver
        // ZonePricingService), só cai no cálculo global de reboque
        // (TarifaService) quando não houver zona/regra aplicável na origem.
        require_once __DIR__ . '/../Pricing/ZonePricingService.php';
        require_once __DIR__ . '/../../Models/Catalog/ServiceType.php';
        require_once __DIR__ . '/../../Models/Cidade.php';
        $codigoReboque = (isset($veiculo['tipo']) && (string)$veiculo['tipo'] === 'moto') ? 'TOW_MOTORCYCLE' : 'TOW_CAR';
        $tipoReboque = ServiceType::buscarPorCodigo($codigoReboque);
        $custoReboque = null;
        if ($tipoReboque) {
            $zonaPreco = ZonePricingService::calcularPreco(
                (float)$pedido['lat_origem'], (float)$pedido['lng_origem'],
                (int)$tipoReboque['id'], (string)($veiculo['tipo'] ?? ''), $distanciaKm
            );
            if ($zonaPreco !== null) {
                $custoReboque = $zonaPreco['valor'];
            }
        }
        if ($custoReboque === null) {
            // §PRECO-POR-CIDADE-01: mesma resolução de cidade-alvo por
            // coordenada usada em ClienteController, aplicada aqui pro
            // recálculo de reboque pós-conversão pane->reboque.
            $cidadeResolvida = Cidade::resolverPorCoordenada((float)$pedido['lat_origem'], (float)$pedido['lng_origem']);
            $tarifa = TarifaService::calcularDetalhado($distanciaKm, $categoria, false, null, $cidadeResolvida['id'] ?? null);
            $custoReboque = (float)($tarifa['valor'] ?? 0);
        }

        $pdo = getPDO();

        // §LOCK-CONVERSAO-01 (27/07/2026, endurecimento pedido em revisão):
        // o guard de noop acima (via transition()) já impede que duas
        // chamadas CONCORRENTES E IDÊNTICAS dupliquem o processamento — sob
        // MySQL real, o SELECT...FOR UPDATE de transition() serializa as
        // duas, e a segunda só prossegue depois que a primeira já commitou
        // a mudança de status, encontrando-a em noop. Mas entre aquele
        // commit e o restante deste método (arquivar pagamento, calcular
        // crédito, gravar destino/custo) não havia transação nem lock algum
        // — uma falha no meio desse trecho (processo derrubado, etc.)
        // podia deixar o pedido preso em 'conversao_aprovada_cliente' com a
        // linha de pagamentos já resetada, sem trilha atômica. Trava e
        // revalida o status sob lock aqui, e faz arquivamento + cálculo de
        // crédito + gravação de destino/custo como uma unidade atômica só.
        try {
            $pdo->beginTransaction();
            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmtLock = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?" . $lockClause);
            $stmtLock->execute([$pedidoId]);
            $statusSobLock = (string)($stmtLock->fetchColumn() ?: '');

            if ($statusSobLock !== 'conversao_aprovada_cliente') {
                // Perdeu a corrida para outra chamada concorrente entre o
                // commit de transition() e este lock — mesmo tratamento do
                // guard de noop acima: devolve sem reprocessar.
                $pdo->rollBack();
                Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
                    "Conversão já avançou pra outro status entre a transição e o lock — pedido #{$pedidoId} (chamada concorrente ganhou a corrida).",
                    ['pedido_id' => $pedidoId, 'status_encontrado' => $statusSobLock]);
                return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['conversao' => 'ja_processada']);
            }

            // Arquiva o pagamento aprovado do socorro no local ANTES de
            // calcular o crédito — o valor arquivado é a fonte inequívoca
            // (nunca mais uma query solta em `pagamentos` que não
            // distinguia qual fase estava sendo lida) e, de quebra, libera
            // a linha de `pagamentos` para a cobrança complementar
            // (reiniciarTentativa() só reaproveita linha não-aprovada). Sem
            // pagamento aprovado prévio = inconsistência de dados real (um
            // socorro no local nunca chega em conversao_reboque_pendente
            // sem ter sido pago antes) — bloqueia em vez de seguir cobrando
            // "no escuro".
            $arquivado = Pagamento::arquivarParaCobrancaComplementar($pedidoId);
            if ($arquivado === false) {
                $pdo->rollBack();
                Logger::log(Logger::LEVEL_ERROR, 'ConversionService', 'decidirConversao', 'conversao',
                    "Conversão bloqueada — pedido #{$pedidoId} não tem pagamento aprovado do socorro no local (inconsistência de dados).",
                    ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId]);
                return PedidoTransitionResult::failure(
                    'Não foi possível localizar o pagamento aprovado do socorro no local para calcular a cobrança complementar. Conversão bloqueada — procure o suporte.'
                );
            }

            // §CREDITO-CONVERSAO-01: credito = min(valor pago no socorro
            // local × percentual, teto em R$), abatido do custoReboque
            // antes de gravar/cobrar. Defaults de produção (30%/R$40) se a
            // config faltar.
            $valorPagoOnSite = (float)$arquivado['valor_total'];
            $creditoPercentual = (float)Configuracao::get('credito_conversao_percentual', 0.30);
            $creditoMaximo = (float)Configuracao::get('credito_conversao_maximo', 40.00);
            $creditoConversao = max(0.0, round(min($valorPagoOnSite * $creditoPercentual, $creditoMaximo), 2));
            if ($creditoConversao > 0) {
                $custoReboqueAntesCredito = $custoReboque;
                $custoReboque = max(0.0, round($custoReboque - $creditoConversao, 2));
                Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
                    "Crédito de conversão aplicado — pedido #{$pedidoId}: R$ {$creditoConversao} abatido (R$ {$custoReboqueAntesCredito} -> R$ {$custoReboque})",
                    ['pedido_id' => $pedidoId, 'valor_pago_onsite' => $valorPagoOnSite, 'credito_percentual' => $creditoPercentual, 'credito_maximo' => $creditoMaximo, 'credito_aplicado' => $creditoConversao]);
            }

            // Destino/distância/custo gravados ANTES da transição de
            // status, nos dois caminhos — preservados sem alteração
            // indevida depois que o checkout começar (nenhum outro código
            // escreve nesses campos enquanto o pedido aguarda o pagamento
            // complementar).
            $pdo->prepare(
                "UPDATE pedidos
                    SET lat_destino = ?, lng_destino = ?, endereco_destino = ?,
                        distancia_km = ?, custo_estimado = ?
                  WHERE id = ?"
            )->execute([$destinoLat, $destinoLng, $destinoEndereco, $distanciaKm, $custoReboque, $pedidoId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception('ConversionService', 'decidirConversao', 'conversao', $e, ['pedido_id' => $pedidoId]);
            return PedidoTransitionResult::failure('Erro interno ao processar a cobrança complementar da conversão.');
        }

        Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
            "Cobrança complementar de reboque calculada — pedido #{$pedidoId}: R$ {$custoReboque} ({$distanciaKm}km, híbrido=" . ($ehHibrido ? 'sim' : 'não') . ")",
            ['pedido_id' => $pedidoId, 'distancia_km' => $distanciaKm, 'custo_reboque' => $custoReboque, 'destino' => $destinoEndereco, 'hibrido' => $ehHibrido]);

        if ($ehHibrido) {
            return self::finalizarCaminhoHibrido($pedidoId, $clienteId, $guinchoAtualId, $custoReboque);
        }

        return self::finalizarCaminhoNaoHibrido($pdo, $pedidoId, $clienteId, $guinchoAtualId, $custoReboque);
    }

    /**
     * O prestador atual é elegível para o caminho híbrido? Reboque_aprovado
     * (guinchos) E capacidade de reboque aprovada (provider_capabilities)
     * checados juntos — os dois sistemas de aprovação coexistem no código
     * atual (ver assignInternal() para TOWING comum), então revalidar só um
     * deles deixaria passar um prestador suspenso no outro.
     */
    private static function guinchoValidoParaHibrido(int $guinchoId): bool
    {
        require_once __DIR__ . '/../../Models/Guincho.php';
        $guincho = Guincho::buscarPorId($guinchoId);
        if (!$guincho || !(bool)($guincho['aprovado'] ?? false)) {
            return false;
        }
        if (array_key_exists('reboque_aprovado', $guincho) && !(bool)$guincho['reboque_aprovado']) {
            return false;
        }
        return ProviderCapability::possuiCapacidadeReboqueAprovada($guinchoId);
    }

    /**
     * Híbrido: o prestador atual continua vinculado ao pedido (não solta
     * disponibilidade, não zera guincho_id) — só muda o status para
     * aguardando_pagamento_reboque_hibrido, aguardando o complementar ser
     * pago. attendance_mode vira 'HYBRID' (semântica própria: mesmo mapa de
     * estados do ON_SITE — HybridFlowDefinition estende sem overrides — mas
     * distinguível em relatórios/auditoria). A revalidação de capacidade no
     * MOMENTO do pagamento (pode ter mudado nesse meio-tempo) é feita em
     * PedidoTransitionService::approvePayment(), não aqui.
     */
    private static function finalizarCaminhoHibrido(int $pedidoId, int $clienteId, int $guinchoAtualId, float $custoReboque): PedidoTransitionResult
    {
        $pdo = getPDO();
        $pdo->prepare("UPDATE pedidos SET attendance_mode = 'HYBRID' WHERE id = ?")->execute([$pedidoId]);

        $step2 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'system', 0, $pedidoId, 'aguardando_pagamento_reboque_hibrido'
        ));
        if (!$step2->ok) {
            return $step2;
        }

        Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
            "Conversão aprovada (híbrido) — pedido #{$pedidoId}: prestador #{$guinchoAtualId} segue vinculado, aguardando pagamento do complementar.",
            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'hibrido' => true, 'guincho_id' => $guinchoAtualId]);

        return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], [
            'conversao' => 'aprovada',
            'hibrido' => true,
            'aguardando_pagamento_complementar' => true,
            'custo_reboque' => $custoReboque,
        ]);
    }

    /**
     * Não-híbrido: pedido volta pra fila comum (nova disputa de matching),
     * então precisa se desvincular do prestador anterior e virar TOWING de
     * verdade (mesmo racional documentado antes desta refatoração — ver
     * histórico do arquivo).
     */
    private static function finalizarCaminhoNaoHibrido(PDO $pdo, int $pedidoId, int $clienteId, int $guinchoAtualId, float $custoReboque): PedidoTransitionResult
    {
        $step2 = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'system', 0, $pedidoId, 'aguardando_pagamento'
        ));
        if (!$step2->ok) {
            return $step2;
        }

        // §PORTABILIDADE-SQL-02 (26/07/2026): DATE_ADD(NOW(), INTERVAL ...) é
        // sintaxe MySQL-only — mesmo padrão já corrigido em Pedido::criar,
        // PricingZone::criar e ServicePriceRule::buscarVigente para não
        // quebrar sob SQLite (tests/bootstrap.php). Calculado em PHP em vez
        // de depender de função de data específica do dialeto.
        $expiracaoAceite = (new DateTimeImmutable())->modify('+30 minutes')->format('Y-m-d H:i:s');
        $pdo->prepare(
            "UPDATE pedidos SET attendance_mode = 'TOWING', guincho_id = NULL,
                expiracao_aceite = ?
              WHERE id = ?"
        )->execute([$expiracaoAceite, $pedidoId]);
        if ($guinchoAtualId > 0) {
            $pdo->prepare('UPDATE guinchos SET disponivel = 1 WHERE id = ?')->execute([$guinchoAtualId]);
        }

        Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',
            "Conversão aprovada (não-híbrido) — pedido #{$pedidoId}: aguardando pagamento do complementar de reboque antes de voltar para matching",
            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'hibrido' => false, 'guincho_anterior_id' => $guinchoAtualId]);

        return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], [
            'conversao' => 'aprovada',
            'hibrido' => false,
            'aguardando_pagamento_complementar' => true,
            'custo_reboque' => $custoReboque,
        ]);
    }
}
