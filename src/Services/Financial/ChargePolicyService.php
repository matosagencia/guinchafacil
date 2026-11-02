<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Financial/ChargePolicyService.php
// ROADMAP socorro automotivo - Etapa 11 (financeiro de duas fases).
//
// Pol�tica pura (mesmo esp�rito de TriageRuleEngine - Etapa 2): recebe uma
// situa��o estruturada e devolve QUAIS itens de cobran�a deveriam existir
// para o primeiro-respondente (motoqueiro), sem calcular valores em R$ e
// sem gravar nada no banco. Quem chama esta classe decide os valores
// (via ServicePricingRule/TarifaService) e grava via OrderChargeItem.
//
// PROPOSITALMENTE N�O � CHAMADA POR NENHUM CONTROLLER/SERVICE DE PRODU��O
// AINDA. Gerar itens de cobran�a automaticamente depende de evid�ncia
// (Etapa 6 - Proof-of-Service), que ainda n�o existe. Ligar o gatilho
// autom�tico antes disso seria pagar (ou bloquear) com base em nada.
//
// Tabela situa��o ? pagamento definida explicitamente pelo usu�rio em
// 22/07/2026 - n�o alterar sem reconfirmar.
//
// Princ�pio central (cita��o literal do usu�rio, n�o editar):
// "O motoqueiro n�o deve ser pago por 'resolver o carro'. Ele deve ser
// pago por: comparecer, diagnosticar corretamente e executar o protocolo
// contratado. Resolver � um poss�vel resultado. Identificar corretamente
// que o ve�culo precisa ser rebocado tamb�m � um resultado v�lido e
// valioso."

require_once __DIR__ . '/../../Models/Financial/ChargeCodes.php';

final class ChargePolicyService
{
    public const POLICY_VERSION = 'v1';
    public const DIRECT_RESCUE_FEE = 'DIRECT_RESCUE_FEE';
    public const TOWING_INTERMEDIATION_FEE = 'TOWING_INTERMEDIATION_FEE';
    public const WORKSHOP_REFERRAL = 'WORKSHOP_REFERRAL';
    public const CONTINUITY_DISCOUNT_RATE = 0.21;

    public static function getDirectRescueFee(): float
    {
        return self::moneyFromConfig(['direct_rescue_fee', 'taxa_resgate_direto_padrao'], 0.0);
    }

    public static function getTowingIntermediationFee(): float
    {
        return self::moneyFromConfig(['towing_intermediation_fee', 'taxa_intermediacao_reboque'], 0.0);
    }

    public static function getWorkshopReferralFee(): float
    {
        return self::moneyFromConfig(['workshop_referral_fee', 'taxa_indicacao_oficina'], 30.0);
    }

    public static function calculateContinuityDiscount(float $intermediation): float
    {
        return round(max(0.0, $intermediation) * self::CONTINUITY_DISCOUNT_RATE, 2);
    }

    public static function applyContinuityDiscount(float $freight, float $intermediation): array
    {
        $discount = self::calculateContinuityDiscount($intermediation);
        $finalIntermediation = round(max(0.0, $intermediation - $discount), 2);

        return [
            'frete' => round(max(0.0, $freight), 2),
            'intermediacao_original' => round(max(0.0, $intermediation), 2),
            'desconto_continuidade' => $discount,
            'intermediacao_final' => $finalIntermediation,
            'total' => round(max(0.0, $freight) + $finalIntermediation, 2),
        ];
    }

    private static function moneyFromConfig(array $keys, float $default): float
    {
        require_once __DIR__ . '/../../Models/Configuracao.php';
        foreach ($keys as $key) {
            $value = Configuracao::get($key, null);
            if ($value !== null && $value !== '') {
                return round(max(0.0, (float)$value), 2);
            }
        }
        return round(max(0.0, $default), 2);
    }

    /**
     * @param string $situationCode Um dos ChargeCodes::SITUATION_*.
     * @param array  $context       Flags estruturais que desambiguam a situa��o:
     *   - diagnostico_concluido (bool): diagn�stico j� tinha sido efetivamente
     *     realizado quando a situa��o ocorreu (relevante p/ CANCELLED_DURING_SERVICE
     *     e SAFETY_RISK_INTERRUPTION).
     *   - pecas_autorizadas (bool): houve pe�as autorizadas pelo cliente
     *     (relevante p/ RESOLVED_ON_SITE).
     *   - servico_executado_ate_falha (array<string>): lista de charge_types
     *     efetivamente executados antes de uma falha de plataforma
     *     (relevante p/ PLATFORM_FAILURE_AFTER_ARRIVAL).
     *
     * @return array<int, array{phase_code:string, charge_type:string, payable_status:string, evidence_required:bool, block_reason_code:?string}>
     *   Lista estrutural de itens a criar para o PRIMEIRO-RESPONDENTE
     *   (motoqueiro). Itens de reboque (fase TOWING, outro prestador) n�o
     *   s�o decididos aqui - pertencem ao fluxo/prestador de reboque, que
     *   j� existe separadamente (TarifaService) e � acionado quando a
     *   situa��o permite convers�o (ver OTHER_PROVIDER_EXECUTES_TOWING).
     */
    public static function resolverItensPrimeiroRespondente(string $situationCode, array $context = []): array
    {
        if (!in_array($situationCode, ChargeCodes::SITUATIONS, true)) {
            throw new \InvalidArgumentException("Situa��o financeira desconhecida: {$situationCode}");
        }

        $diagnosticoConcluido = !empty($context['diagnostico_concluido']);
        $pecasAutorizadas = !empty($context['pecas_autorizadas']);

        switch ($situationCode) {
            case ChargeCodes::SITUATION_RESOLVED_ON_SITE:
                // Deslocamento + diagn�stico + servi�o executado + pe�as, quando houver.
                $itens = [
                    self::item(ChargeCodes::PHASE_INITIAL_ASSISTANCE, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE),
                    self::item(ChargeCodes::PHASE_ON_SITE_SERVICE, ChargeCodes::TYPE_LABOR_FEE),
                ];
                if ($pecasAutorizadas) {
                    $itens[] = self::item(ChargeCodes::PHASE_PARTS_SUPPLY, ChargeCodes::TYPE_PARTS_FEE);
                }
                return $itens;

            case ChargeCodes::SITUATION_TOWING_RECOMMENDED_ACCEPTED:
            case ChargeCodes::SITUATION_TOWING_RECOMMENDED_REFUSED:
                // Deslocamento + diagn�stico integral (n�o h� LABOR_FEE - n�o consertou,
                // mas identificar corretamente a necessidade de reboque � um resultado
                // v�lido e remunerado igual nos dois casos: aceito ou recusado pelo cliente).
                return [
                    self::item(ChargeCodes::PHASE_INITIAL_ASSISTANCE, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE),
                ];

            case ChargeCodes::SITUATION_CANCELLED_DURING_SERVICE:
                // Deslocamento + taxa de comparecimento sempre; diagn�stico s� se j�
                // tiver sido efetivamente realizado antes do cancelamento.
                $itens = [
                    self::item(ChargeCodes::PHASE_CANCELLATION, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_CANCELLATION, ChargeCodes::TYPE_ATTENDANCE_FEE),
                ];
                if ($diagnosticoConcluido) {
                    $itens[] = self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE);
                }
                return $itens;

            case ChargeCodes::SITUATION_NO_SHOW:
                // Sem pagamento.
                return [];

            case ChargeCodes::SITUATION_MISSING_REQUIRED_EVIDENCE:
                // Itens existem (o trabalho foi declarado), mas o repasse fica
                // bloqueado para revis�o - n�o pago automaticamente, n�o negado
                // de cara. Reaproveita o mesmo desenho de RESOLVED_ON_SITE porque
                // a aus�ncia de evid�ncia normalmente aparece depois do servi�o
                // j� ter sido declarado como executado.
                return array_map(
                    fn (array $item) => self::bloquear($item, 'EVIDENCE_MISSING'),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_INCONSISTENT_DIAGNOSIS:
                // Retido para auditoria - n�o � bloqueio definitivo nem pagamento.
                return array_map(
                    fn (array $item) => self::revisar($item, 'DIAGNOSIS_UNDER_AUDIT'),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_CONFIRMED_FRAUD:
                // Pagamento recusado/estornado, com penalidade ao prestador. A
                // penalidade em si (ex.: suspens�o de capacidade) � tratada fora
                // desta pol�tica, via ProviderCapability::suspender() + Demanda.
                return array_map(
                    fn (array $item) => self::rejeitarPorFraude($item),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_CUSTOMER_ABSENT_AFTER_ARRIVAL:
                // Deslocamento + taxa de comparecimento, conforme pol�tica de
                // toler�ncia (a pol�tica de toler�ncia em si - tempo m�nimo de
                // espera etc. - � operacional, n�o financeira, e fica fora desta
                // classe; aqui s� garantimos que o item existe).
                return [
                    self::item(ChargeCodes::PHASE_NO_SHOW, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_NO_SHOW, ChargeCodes::TYPE_ATTENDANCE_FEE),
                ];

            case ChargeCodes::SITUATION_SAFETY_RISK_INTERRUPTION:
                // Deslocamento + comparecimento + diagn�stico executado, conforme
                // evid�ncias (n�o presume diagn�stico completo - depende do contexto).
                $itens = [
                    self::item(ChargeCodes::PHASE_INITIAL_ASSISTANCE, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_CANCELLATION, ChargeCodes::TYPE_ATTENDANCE_FEE),
                ];
                if ($diagnosticoConcluido) {
                    $itens[] = self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE);
                }
                return $itens;

            case ChargeCodes::SITUATION_PLATFORM_FAILURE_AFTER_ARRIVAL:
                // Pagamento garantido da etapa comprovadamente executada - a culpa
                // � da plataforma, ent�o o que j� foi feito (conforme evid�ncias/
                // contexto) � eleg�vel diretamente, sem passar por revis�o.
                $executados = $context['servico_executado_ate_falha'] ?? [ChargeCodes::TYPE_DISPATCH_FEE];
                return array_map(
                    fn (string $chargeType) => self::item(
                        self::inferirFaseParaTipo($chargeType),
                        $chargeType,
                        ChargeCodes::PAYABLE_ELIGIBLE
                    ),
                    $executados
                );

            case ChargeCodes::SITUATION_OTHER_PROVIDER_EXECUTES_TOWING:
                // Informativo: n�o gera itens pr�prios. O motoqueiro recebe pela
                // situa��o que efetivamente ocorreu na fase dele (normalmente
                // TOWING_RECOMMENDED_ACCEPTED); o guincheiro recebe a etapa de
                // reboque separadamente. Chamar esta situa��o diretamente � erro
                // de uso - sinaliza para o chamador resolver a situa��o real do
                // motoqueiro primeiro.
                throw new \LogicException(
                    'OTHER_PROVIDER_EXECUTES_TOWING n�o gera itens diretamente - resolva a situa��o real ' .
                    'do primeiro-respondente (ex.: TOWING_RECOMMENDED_ACCEPTED) e trate o reboque como pedido/fase separada.'
                );

            default:
                // Inalcan��vel - guard-clause no topo j� validou contra ChargeCodes::SITUATIONS.
                throw new \LogicException("Situa��o sem regra implementada: {$situationCode}");
        }
    }

    public static function itemIndicacaoOficina(float $feeAmount, array $snapshot = []): array
    {
        if ($feeAmount < 0) {
            throw new \InvalidArgumentException('A taxa de indica��o n�o pode ser negativa.');
        }

        return [
            'phase_code' => ChargeCodes::PHASE_WORKSHOP_REFERRAL,
            'charge_type' => ChargeCodes::TYPE_REFERRAL_FEE,
            'description' => 'Taxa por indica��o de oficina parceira',
            'quantity' => 1.0,
            'unit_amount' => round($feeAmount, 2),
            'gross_amount' => round($feeAmount, 2),
            'platform_fee_amount' => round($feeAmount, 2),
            'provider_net_amount' => 0.0,
            'charge_status' => ChargeCodes::CHARGE_PENDING,
            'payable_status' => ChargeCodes::PAYABLE_NOT_ELIGIBLE,
            'calculation_version' => (string)($snapshot['rule_version'] ?? self::POLICY_VERSION),
            'calculation_context' => $snapshot,
            'evidence_required' => true,
        ];
    }

    public static function criarCobrancaIndicacaoOficina(
        int $orderId,
        int $providerId,
        float $feeAmount,
        array $snapshot,
        string $idempotencyKey
    ): array {
        require_once __DIR__ . '/../../Models/Financial/OrderChargeItem.php';
        return OrderChargeItem::criar(array_merge(
            self::itemIndicacaoOficina($feeAmount, $snapshot),
            [
                'order_id' => $orderId,
                'provider_id' => $providerId,
                'idempotency_key' => $idempotencyKey,
            ]
        ));
    }

    public static function itemResgateDireto(float $feeAmount, array $snapshot = []): array
    {
        if ($feeAmount < 0) {
            throw new \InvalidArgumentException('A taxa de resgate direto n�o pode ser negativa.');
        }
        return [
            'phase_code' => ChargeCodes::PHASE_DIRECT_RESCUE,
            'charge_type' => ChargeCodes::TYPE_DIRECT_RESCUE_FEE,
            'description' => 'Intermedia��o de resgate direto por oficina',
            'quantity' => 1,
            'unit_amount' => $feeAmount,
            'gross_amount' => $feeAmount,
            'discount_amount' => 0,
            'platform_fee_amount' => $feeAmount,
            'provider_net_amount' => 0,
            'charge_status' => ChargeCodes::CHARGE_PENDING,
            'payable_status' => ChargeCodes::PAYABLE_NOT_ELIGIBLE,
            'calculation_version' => (string)($snapshot['rule_version'] ?? self::POLICY_VERSION),
            'calculation_context' => $snapshot,
            'evidence_required' => true,
        ];
    }

    public static function criarCobrancasResgateDireto(int $orderId, int $providerId, float $referralFee, float $directFee, array $snapshot): array
    {
        require_once __DIR__ . '/../../Models/Financial/OrderChargeItem.php';
        return [
            'referral' => self::criarCobrancaIndicacaoOficina($orderId, $providerId, $referralFee, $snapshot, 'referral_fee:' . $orderId),
            'direct_rescue' => OrderChargeItem::criar(array_merge(self::itemResgateDireto($directFee, $snapshot), [
                'order_id' => $orderId,
                'provider_id' => $providerId,
                'idempotency_key' => 'direct_rescue_fee:' . $orderId,
            ])),
        ];
    }

    private static function item(
        string $phaseCode,
        string $chargeType,
        string $payableStatus = ChargeCodes::PAYABLE_PENDING_EVIDENCE
    ): array {
        return [
            'phase_code' => $phaseCode,
            'charge_type' => $chargeType,
            'payable_status' => $payableStatus,
            'evidence_required' => $payableStatus === ChargeCodes::PAYABLE_PENDING_EVIDENCE,
            'block_reason_code' => null,
        ];
    }

    private static function bloquear(array $item, string $reasonCode): array
    {
        $item['payable_status'] = ChargeCodes::PAYABLE_BLOCKED;
        $item['block_reason_code'] = $reasonCode;
        return $item;
    }

    private static function revisar(array $item, string $reasonCode): array
    {
        $item['payable_status'] = ChargeCodes::PAYABLE_PENDING_REVIEW;
        $item['block_reason_code'] = $reasonCode;
        return $item;
    }

    private static function rejeitarPorFraude(array $item): array
    {
        $item['payable_status'] = ChargeCodes::PAYABLE_REVERSED;
        $item['block_reason_code'] = 'FRAUD_CONFIRMED';
        return $item;
    }

    private static function inferirFaseParaTipo(string $chargeType): string
    {
        return match ($chargeType) {
            ChargeCodes::TYPE_DIAGNOSIS_FEE => ChargeCodes::PHASE_ON_SITE_DIAGNOSIS,
            ChargeCodes::TYPE_LABOR_FEE => ChargeCodes::PHASE_ON_SITE_SERVICE,
            ChargeCodes::TYPE_PARTS_FEE => ChargeCodes::PHASE_PARTS_SUPPLY,
            default => ChargeCodes::PHASE_INITIAL_ASSISTANCE,
        };
    }
}
