<?php



declare(strict_types=1);



// File: guinchafacil/src/Services/Conversion/ConversionService.php

// ROADMAP socorro automotivo - Etapa 7 (convers�o de socorro local para reboque).

//

// Decide o que acontece depois que o cliente aprova a convers�o

// (conversao_reboque_pendente ? conversao_aprovada_cliente):

//   - prestador atual j� tem capacidade de REBOQUE aprovada (h�brido) ?

//     continua vinculado ao mesmo pedido, sem nova disputa de matching, mas

//     PRECISA pagar o complementar de reboque antes de preparacao_veiculo

//     (aguardando_pagamento_reboque_hibrido - ver �HIBRIDO-COMPLEMENTAR-01);

//   - sen�o ? pedido volta para aguardando_pagamento com uma cobran�a

//     COMPLEMENTAR real do reboque (ver �COBRANCA-REBOQUE-01 abaixo); s�

//     depois de pago � que o matching por capacidade (Etapa 4) entra em jogo

//     a partir de aguardando_guincho.

//

// �COBRANCA-REBOQUE-01 (24/07/2026, corre��o de lacuna apontada pelo

// usu�rio): o valor pago pelo socorro no local (deslocamento + diagn�stico)

// N�O cobre o reboque - s�o servi�os com precifica��o diferente. Antes

// desta corre��o, decidirConversao() soltava o pedido de volta pra fila de

// reboque SEM NUNCA cobrar o cliente por isso e SEM NUNCA coletar o

// endere�o de destino (o pedido de socorro no local corretamente n�o exige

// destino upfront - service_types.requires_destination=0 - mas algu�m

// precisa perguntar "para onde rebocar" quando o reboque de fato se torna

// necess�rio). Esta classe: (1) exige que o chamador informe o destino j�

// geocodificado (endereco/lat/lng) nesse momento - nos DOIS caminhos, desde

// �HIBRIDO-COMPLEMENTAR-01; (2) calcula o valor do reboque com a MESMA

// tarifa usada por qualquer pedido de reboque comum (zona de precifica��o

// ou TarifaService::calcularDetalhado como fallback); (3) grava esse valor

// como novo custo_estimado do pedido.

//

// §CREDITO-CONVERSAO-01 (26/07/2026): o abatimento no reboque complementar

// pelo valor j� pago no socorro no local (credito_conversao_percentual/

// credito_conversao_maximo em Configuracao) � aplicado nos DOIS caminhos.

//

// �HIBRIDO-COMPLEMENTAR-01 (27/07/2026, corre��o de lacuna apontada pelo

// usu�rio): o caminho h�brido pulava direto pra preparacao_veiculo sem

// cobrar nada - mesmo bug de fundo do n�o-h�brido, nunca corrigido. Agora os

// dois caminhos compartilham EXATAMENTE o mesmo c�lculo de destino/custo/

// cr�dito; o que muda entre eles � s� o status de destino p�s-cobran�a

// (preparacao_veiculo direto vs. nova disputa de matching) - ver

// PedidoTransitionService::approvePayment() para o branch p�s-pagamento e a

// revalida��o de capacidade do prestador h�brido no momento da aprova��o

// (pode ter perdido a aprova��o/capacidade entre a decis�o de convers�o e o

// pagamento efetivo do complementar).

//

// Descoberta ao implementar §HIBRIDO-COMPLEMENTAR-01: `pagamentos` tem

// UNIQUE(pedido_id) e Pagamento::reiniciarTentativa() recusa (de prop�sito)

// reaproveitar a linha quando o status j� � 'aprovado' - ou seja, a cobran�a

// complementar (h�brida OU n�o-h�brida) na pr�tica NUNCA conseguia nem ser

// criada: PagamentoController::mercadoPagoTransparente() chama

// Pagamento::criar() de novo quando o pedido volta pra aguardando_pagamento,

// e isso batia direto nessa guarda. Corrigido via

// Pagamento::arquivarParaCobrancaComplementar(), chamado logo abaixo - ver

// o m�todo para o racional completo.



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
require_once __DIR__ . '/ConversionPriceSnapshotService.php';



final class ConversionService

{

    /**

     * Cliente decide se aceita a convers�o para reboque.

     * Recusa: fica em conversao_reboque_pendente (nenhum cancelamento

     * autom�tico - mesma pol�tica j� adotada para or�amento recusado e

     * falha de pagamento: resolu��o manual via admin/Demanda).

     *

     * @param array{endereco?:string,lat?:float,lng?:float} $destino Obrigat�rio

     *   quando $aprovado=true (h�brido ou n�o - os dois caminhos cobram o

     *   complementar de reboque desde §HIBRIDO-COMPLEMENTAR-01). Ignorado

     *   na recusa.

     */

    public static function decidirConversao(int $pedidoId, int $clienteId, bool $aprovado, array $destino = []): PedidoTransitionResult

    {

        if (!$aprovado) {

            Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',

                "Convers�o para reboque recusada pelo cliente - pedido #{$pedidoId}",

                ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId]);

            return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['conversao' => 'recusada']);

        }



        // B-011: A convers�o complementar exige snapshot pr�prio imut�vel.

        // A tabela atual order_price_snapshots � UNIQUE(order_id) e pertence

        // � cota��o original ON_SITE. N�o cobrar substituindo esse snapshot.

        // Remover o bloqueio SOMENTE com migration+ledger+QA de convers�o.

        // O snapshot complementar � gravado atomically with the conversion below.



        // B-011: cotar ANTES de transicionar ou arquivar pagamento.

        $prePedido = Pedido::buscarPorId($pedidoId);

        if (!$prePedido || (int)($prePedido['cliente_id'] ?? 0) !== $clienteId) {

            return PedidoTransitionResult::failure('PRICE-ORDER-UNAUTHORIZED');

        }
        $pagarNaChegada = strtolower(trim((string)($prePedido['forma_pagamento_escolhida'] ?? ''))) === 'pago_na_chegada';

        $destinoLatPrev = isset($destino['lat']) ? (float)$destino['lat'] : null;

        $destinoLngPrev = isset($destino['lng']) ? (float)$destino['lng'] : null;

        $destinoEnderecoPrev = trim((string)($destino['endereco'] ?? ''));

        if ($destinoLatPrev === null || $destinoLngPrev === null || $destinoEnderecoPrev === ''

            || !GeoService::coordenadasValidas($destinoLatPrev, $destinoLngPrev)) {

            return PedidoTransitionResult::failure('PRICE-DESTINATION-REQUIRED');

        }

        require_once __DIR__ . '/../Pricing/CotacaoUnificadaService.php';

        require_once __DIR__ . '/../../Models/Veiculo.php';

        $veiculoPrev = !empty($prePedido['veiculo_id'])

            ? Veiculo::buscarPorId((int)$prePedido['veiculo_id']) : null;

        $categoriaPrev = $veiculoPrev ? TarifaService::categoriaDeVeiculo($veiculoPrev) : 'popular';

        $codigoPrev = ($categoriaPrev === 'moto') ? 'TOW_MOTORCYCLE' : 'TOW_CAR';

        $preQuote = CotacaoUnificadaService::calcular([

            'service_code' => $codigoPrev,

            'lat_origem' => (float)$prePedido['lat_origem'],

            'lng_origem' => (float)$prePedido['lng_origem'],

            'lat_destino' => $destinoLatPrev,

            'lng_destino' => $destinoLngPrev,

            'vehicle_category' => $categoriaPrev,

        ]);

        if (!($preQuote['ok'] ?? false)) {

            return PedidoTransitionResult::failure((string)($preQuote['erro'] ?? 'PRICE-CALCULATION-FAILED'));

        }



        $step1 = PedidoTransitionService::transition(new PedidoTransitionRequest(

            'cliente', $clienteId, $pedidoId, 'conversao_aprovada_cliente'

        ));

        if (!$step1->ok) {

            return $step1;

        }



        // §CONCORRENCIA-01 (27/07/2026): transition() trata from===to como

        // noop bem-sucedido (compatibilidade retroativa) - o que significa

        // que uma chamada REPETIDA de decidirConversao() para um pedido que

        // j� saiu de 'conversao_reboque_pendente' (retry de rede, duplo

        // clique do cliente, reenvio de request) chegaria at� aqui com

        // $step1->ok=true sem ter feito nenhuma transi��o de verdade. Sem

        // este guard, o c�digo abaixo arquivaria o pagamento (j� arquivado

        // na primeira chamada) e aplicaria o cr�dito DE NOVO. Curto-circuita

        // aqui: devolve o estado atual sem reprocessar nada.

        if (!empty($step1->context['noop'])) {

            Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',

                "Convers�o j� havia sido processada - pedido #{$pedidoId} n�o est� mais em conversao_reboque_pendente (chamada duplicada ignorada).",

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

                'Informe o endere�o para onde o ve�culo ser� rebocado antes de aprovar a convers�o.'

            );

        }



        // Usa exatamente o mesmo motor e a mesma rota j� calculados no preflight.

        // O cr�dito de convers�o continua item financeiro separado, auditado adiante.

        $distanciaKm = (float)$preQuote['km_transporte'];

        $custoReboque = (float)$preQuote['preco_cliente'];



        $pdo = getPDO();



        // �LOCK-CONVERSAO-01 (27/07/2026, endurecimento pedido em revis�o):

        // o guard de noop acima (via transition()) j� impede que duas

        // chamadas CONCORRENTES E ID�NTICAS dupliquem o processamento - sob

        // MySQL real, o SELECT...FOR UPDATE de transition() serializa as

        // duas, e a segunda s� prossegue depois que a primeira j� commitou

        // a mudan�a de status, encontrando-a em noop. Mas entre aquele

        // commit e o restante deste m�todo (arquivar pagamento, calcular

        // cr�dito, gravar destino/custo) n�o havia transa��o nem lock algum

        // - uma falha no meio desse trecho (processo derrubado, etc.)

        // podia deixar o pedido preso em 'conversao_aprovada_cliente' com a

        // linha de pagamentos j� resetada, sem trilha at�mica. Trava e

        // revalida o status sob lock aqui, e faz arquivamento + c�lculo de

        // cr�dito + grava��o de destino/custo como uma unidade at�mica s�.

        try {

            $pdo->beginTransaction();

            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';

            $stmtLock = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?" . $lockClause);

            $stmtLock->execute([$pedidoId]);

            $statusSobLock = (string)($stmtLock->fetchColumn() ?: '');



            if ($statusSobLock !== 'conversao_aprovada_cliente') {

                // Perdeu a corrida para outra chamada concorrente entre o

                // commit de transition() e este lock - mesmo tratamento do

                // guard de noop acima: devolve sem reprocessar.

                $pdo->rollBack();

                Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',

                    "Convers�o j� avan�ou pra outro status entre a transi��o e o lock - pedido #{$pedidoId} (chamada concorrente ganhou a corrida).",

                    ['pedido_id' => $pedidoId, 'status_encontrado' => $statusSobLock]);

                return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['conversao' => 'ja_processada']);

            }



            // Pagamento em dinheiro n�o possui aprova��o de gateway. Nesse
            // ramo, a convers�o recalcula o valor e devolve o pedido � fila;
            // a confirma��o financeira ocorre no encerramento do atendimento.
            if ($pagarNaChegada) {
                $pdo->prepare("UPDATE pedidos SET lat_destino=?, lng_destino=?, endereco_destino=?, distancia_km=?, custo_estimado=?, status='aguardando_guincho', guincho_id=NULL, attendance_mode='TOWING' WHERE id=?")
                    ->execute([$destinoLat, $destinoLng, $destinoEndereco, $distanciaKm, $custoReboque, $pedidoId]);
                ConversionPriceSnapshotService::persist($pdo, $pedidoId, $preQuote);
                // Pagamento em dinheiro fica pendente ate o parceiro receber
                // no local de origem; o pedido, porem, ja entra na fila.
                $stmtPagamento = $pdo->prepare('SELECT id FROM pagamentos WHERE pedido_id = ? LIMIT 1');
                $stmtPagamento->execute([$pedidoId]);
                if (!$stmtPagamento->fetchColumn()) {
                    $pagamentoId = Pagamento::criar($pedidoId, 'dinheiro', $custoReboque, $custoReboque, 0.0);
                    if ($pagamentoId === false) {
                        throw new RuntimeException('Nao foi possivel registrar o pagamento em dinheiro pendente.');
                    }
                }
                PedidoEventoService::registrar($pdo, $pedidoId, 'conversao_dinheiro_fila', 'conversao_aprovada_cliente', 'aguardando_guincho', 'cliente', $clienteId, 'pago_na_chegada');
                $pdo->commit();
                return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], ['conversao' => 'pago_na_chegada', 'cobranca_online' => false]);
            }

            // Arquiva o pagamento aprovado do socorro no local ANTES de

            // calcular o cr�dito - o valor arquivado � a fonte inequ�voca

            // (nunca mais uma query solta em `pagamentos` que n�o

            // distinguia qual fase estava sendo lida) e, de quebra, libera

            // a linha de `pagamentos` para a cobran�a complementar

            // (reiniciarTentativa() s� reaproveita linha n�o-aprovada). Sem

            // pagamento aprovado pr�vio = inconsist�ncia de dados real (um

            // socorro no local nunca chega em conversao_reboque_pendente

            // sem ter sido pago antes) - bloqueia em vez de seguir cobrando

            // "no escuro".

            $arquivado = Pagamento::arquivarParaCobrancaComplementar($pedidoId);

            if ($arquivado === false) {

                $pdo->rollBack();

                Logger::log(Logger::LEVEL_ERROR, 'ConversionService', 'decidirConversao', 'conversao',

                    "Convers�o bloqueada - pedido #{$pedidoId} n�o tem pagamento aprovado do socorro no local (inconsist�ncia de dados).",

                    ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId]);

                return PedidoTransitionResult::failure(

                    'N�o foi poss�vel localizar o pagamento aprovado do socorro no local para calcular a cobran�a complementar. Convers�o bloqueada - procure o suporte.'

                );

            }



            // §CREDITO-CONVERSAO-01: credito = min(valor pago no socorro

            // local × percentual, teto em R$), abatido do custoReboque

            // antes de gravar/cobrar. Defaults de produ��o (30%/R$40) se a

            // config faltar.

            $valorPagoOnSite = (float)$arquivado['valor_total'];

            $creditoPercentual = (float)Configuracao::get('credito_conversao_percentual', 0.30);

            $creditoMaximo = (float)Configuracao::get('credito_conversao_maximo', 40.00);

            $creditoConversao = max(0.0, round(min($valorPagoOnSite * $creditoPercentual, $creditoMaximo), 2));

            if ($creditoConversao > 0) {

                $custoReboqueAntesCredito = $custoReboque;

                $custoReboque = max(0.0, round($custoReboque - $creditoConversao, 2));

                Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',

                    "Cr�dito de convers�o aplicado - pedido #{$pedidoId}: R$ {$creditoConversao} abatido (R$ {$custoReboqueAntesCredito} -> R$ {$custoReboque})",

                    ['pedido_id' => $pedidoId, 'valor_pago_onsite' => $valorPagoOnSite, 'credito_percentual' => $creditoPercentual, 'credito_maximo' => $creditoMaximo, 'credito_aplicado' => $creditoConversao]);

            }



            // Destino/dist�ncia/custo gravados ANTES da transi��o de

            // status, nos dois caminhos - preservados sem altera��o

            // indevida depois que o checkout come�ar (nenhum outro c�digo

            // escreve nesses campos enquanto o pedido aguarda o pagamento

            // complementar).

            $pdo->prepare(

                "UPDATE pedidos

                    SET lat_destino = ?, lng_destino = ?, endereco_destino = ?,

                        distancia_km = ?, custo_estimado = ?

                  WHERE id = ?"

            )->execute([$destinoLat, $destinoLng, $destinoEndereco, $distanciaKm, $custoReboque, $pedidoId]);
            ConversionPriceSnapshotService::persist($pdo, $pedidoId, $preQuote);



            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            Logger::exception('ConversionService', 'decidirConversao', 'conversao', $e, ['pedido_id' => $pedidoId]);

            return PedidoTransitionResult::failure('Erro interno ao processar a cobran�a complementar da convers�o.');

        }



        Logger::log(Logger::LEVEL_INFO, 'ConversionService', 'decidirConversao', 'conversao',

            "Cobran�a complementar de reboque calculada - pedido #{$pedidoId}: R$ {$custoReboque} ({$distanciaKm}km, h�brido=" . ($ehHibrido ? 'sim' : 'n�o') . ")",

            ['pedido_id' => $pedidoId, 'distancia_km' => $distanciaKm, 'custo_reboque' => $custoReboque, 'destino' => $destinoEndereco, 'hibrido' => $ehHibrido]);



        if ($ehHibrido) {

            return self::finalizarCaminhoHibrido($pedidoId, $clienteId, $guinchoAtualId, $custoReboque);

        }



        return self::finalizarCaminhoNaoHibrido($pdo, $pedidoId, $clienteId, $guinchoAtualId, $custoReboque);

    }



    /**

     * O prestador atual � eleg�vel para o caminho h�brido? Reboque_aprovado

     * (guinchos) E capacidade de reboque aprovada (provider_capabilities)

     * checados juntos - os dois sistemas de aprova��o coexistem no c�digo

     * atual (ver assignInternal() para TOWING comum), ent�o revalidar s� um

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

     * H�brido: o prestador atual continua vinculado ao pedido (n�o solta

     * disponibilidade, n�o zera guincho_id) - s� muda o status para

     * aguardando_pagamento_reboque_hibrido, aguardando o complementar ser

     * pago. attendance_mode vira 'HYBRID' (sem�ntica pr�pria: mesmo mapa de

     * estados do ON_SITE - HybridFlowDefinition estende sem overrides - mas

     * distingu�vel em relat�rios/auditoria). A revalida��o de capacidade no

     * MOMENTO do pagamento (pode ter mudado nesse meio-tempo) � feita em

     * PedidoTransitionService::approvePayment(), n�o aqui.

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

            "Convers�o aprovada (h�brido) - pedido #{$pedidoId}: prestador #{$guinchoAtualId} segue vinculado, aguardando pagamento do complementar.",

            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'hibrido' => true, 'guincho_id' => $guinchoAtualId]);



        return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], [

            'conversao' => 'aprovada',

            'hibrido' => true,

            'aguardando_pagamento_complementar' => true,

            'custo_reboque' => $custoReboque,

        ]);

    }



    /**

     * N�o-h�brido: pedido volta pra fila comum (nova disputa de matching),

     * ent�o precisa se desvincular do prestador anterior e virar TOWING de

     * verdade (mesmo racional documentado antes desta refatora��o - ver

     * hist�rico do arquivo).

     */

    private static function finalizarCaminhoNaoHibrido(PDO $pdo, int $pedidoId, int $clienteId, int $guinchoAtualId, float $custoReboque): PedidoTransitionResult

    {

        $step2 = PedidoTransitionService::transition(new PedidoTransitionRequest(

            'system', 0, $pedidoId, 'aguardando_pagamento'

        ));

        if (!$step2->ok) {

            return $step2;

        }



        // �PORTABILIDADE-SQL-02 (26/07/2026): DATE_ADD(NOW(), INTERVAL ...) �

        // sintaxe MySQL-only - mesmo padr�o j� corrigido em Pedido::criar,

        // PricingZone::criar e ServicePriceRule::buscarVigente para n�o

        // quebrar sob SQLite (tests/bootstrap.php). Calculado em PHP em vez

        // de depender de fun��o de data espec�fica do dialeto.

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

            "Convers�o aprovada (n�o-h�brido) - pedido #{$pedidoId}: aguardando pagamento do complementar de reboque antes de voltar para matching",

            ['pedido_id' => $pedidoId, 'cliente_id' => $clienteId, 'hibrido' => false, 'guincho_anterior_id' => $guinchoAtualId]);



        return PedidoTransitionResult::success(Pedido::buscarPorId($pedidoId) ?? [], [

            'conversao' => 'aprovada',

            'hibrido' => false,

            'aguardando_pagamento_complementar' => true,

            'custo_reboque' => $custoReboque,

        ]);

    }

}

