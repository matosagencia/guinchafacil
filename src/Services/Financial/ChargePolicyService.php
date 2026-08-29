<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Financial/ChargePolicyService.php
// ROADMAP socorro automotivo — Etapa 11 (financeiro de duas fases).
//
// Política pura (mesmo espírito de TriageRuleEngine — Etapa 2): recebe uma
// situação estruturada e devolve QUAIS itens de cobrança deveriam existir
// para o primeiro-respondente (motoqueiro), sem calcular valores em R$ e
// sem gravar nada no banco. Quem chama esta classe decide os valores
// (via ServicePricingRule/TarifaService) e grava via OrderChargeItem.
//
// PROPOSITALMENTE NÃO É CHAMADA POR NENHUM CONTROLLER/SERVICE DE PRODUÇÃO
// AINDA. Gerar itens de cobrança automaticamente depende de evidência
// (Etapa 6 — Proof-of-Service), que ainda não existe. Ligar o gatilho
// automático antes disso seria pagar (ou bloquear) com base em nada.
//
// Tabela situação → pagamento definida explicitamente pelo usuário em
// 22/07/2026 — não alterar sem reconfirmar.
//
// Princípio central (citação literal do usuário, não editar):
// "O motoqueiro não deve ser pago por 'resolver o carro'. Ele deve ser
// pago por: comparecer, diagnosticar corretamente e executar o protocolo
// contratado. Resolver é um possível resultado. Identificar corretamente
// que o veículo precisa ser rebocado também é um resultado válido e
// valioso."

require_once __DIR__ . '/../../Models/Financial/ChargeCodes.php';

final class ChargePolicyService
{
    public const POLICY_VERSION = 'v1';

    /**
     * @param string $situationCode Um dos ChargeCodes::SITUATION_*.
     * @param array  $context       Flags estruturais que desambiguam a situação:
     *   - diagnostico_concluido (bool): diagnóstico já tinha sido efetivamente
     *     realizado quando a situação ocorreu (relevante p/ CANCELLED_DURING_SERVICE
     *     e SAFETY_RISK_INTERRUPTION).
     *   - pecas_autorizadas (bool): houve peças autorizadas pelo cliente
     *     (relevante p/ RESOLVED_ON_SITE).
     *   - servico_executado_ate_falha (array<string>): lista de charge_types
     *     efetivamente executados antes de uma falha de plataforma
     *     (relevante p/ PLATFORM_FAILURE_AFTER_ARRIVAL).
     *
     * @return array<int, array{phase_code:string, charge_type:string, payable_status:string, evidence_required:bool, block_reason_code:?string}>
     *   Lista estrutural de itens a criar para o PRIMEIRO-RESPONDENTE
     *   (motoqueiro). Itens de reboque (fase TOWING, outro prestador) não
     *   são decididos aqui — pertencem ao fluxo/prestador de reboque, que
     *   já existe separadamente (TarifaService) e é acionado quando a
     *   situação permite conversão (ver OTHER_PROVIDER_EXECUTES_TOWING).
     */
    public static function resolverItensPrimeiroRespondente(string $situationCode, array $context = []): array
    {
        if (!in_array($situationCode, ChargeCodes::SITUATIONS, true)) {
            throw new \InvalidArgumentException("Situação financeira desconhecida: {$situationCode}");
        }

        $diagnosticoConcluido = !empty($context['diagnostico_concluido']);
        $pecasAutorizadas = !empty($context['pecas_autorizadas']);

        switch ($situationCode) {
            case ChargeCodes::SITUATION_RESOLVED_ON_SITE:
                // Deslocamento + diagnóstico + serviço executado + peças, quando houver.
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
                // Deslocamento + diagnóstico integral (não há LABOR_FEE — não consertou,
                // mas identificar corretamente a necessidade de reboque é um resultado
                // válido e remunerado igual nos dois casos: aceito ou recusado pelo cliente).
                return [
                    self::item(ChargeCodes::PHASE_INITIAL_ASSISTANCE, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE),
                ];

            case ChargeCodes::SITUATION_CANCELLED_DURING_SERVICE:
                // Deslocamento + taxa de comparecimento sempre; diagnóstico só se já
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
                // bloqueado para revisão — não pago automaticamente, não negado
                // de cara. Reaproveita o mesmo desenho de RESOLVED_ON_SITE porque
                // a ausência de evidência normalmente aparece depois do serviço
                // já ter sido declarado como executado.
                return array_map(
                    fn (array $item) => self::bloquear($item, 'EVIDENCE_MISSING'),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_INCONSISTENT_DIAGNOSIS:
                // Retido para auditoria — não é bloqueio definitivo nem pagamento.
                return array_map(
                    fn (array $item) => self::revisar($item, 'DIAGNOSIS_UNDER_AUDIT'),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_CONFIRMED_FRAUD:
                // Pagamento recusado/estornado, com penalidade ao prestador. A
                // penalidade em si (ex.: suspensão de capacidade) é tratada fora
                // desta política, via ProviderCapability::suspender() + Demanda.
                return array_map(
                    fn (array $item) => self::rejeitarPorFraude($item),
                    self::resolverItensPrimeiroRespondente(ChargeCodes::SITUATION_RESOLVED_ON_SITE, $context)
                );

            case ChargeCodes::SITUATION_CUSTOMER_ABSENT_AFTER_ARRIVAL:
                // Deslocamento + taxa de comparecimento, conforme política de
                // tolerância (a política de tolerância em si — tempo mínimo de
                // espera etc. — é operacional, não financeira, e fica fora desta
                // classe; aqui só garantimos que o item existe).
                return [
                    self::item(ChargeCodes::PHASE_NO_SHOW, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_NO_SHOW, ChargeCodes::TYPE_ATTENDANCE_FEE),
                ];

            case ChargeCodes::SITUATION_SAFETY_RISK_INTERRUPTION:
                // Deslocamento + comparecimento + diagnóstico executado, conforme
                // evidências (não presume diagnóstico completo — depende do contexto).
                $itens = [
                    self::item(ChargeCodes::PHASE_INITIAL_ASSISTANCE, ChargeCodes::TYPE_DISPATCH_FEE),
                    self::item(ChargeCodes::PHASE_CANCELLATION, ChargeCodes::TYPE_ATTENDANCE_FEE),
                ];
                if ($diagnosticoConcluido) {
                    $itens[] = self::item(ChargeCodes::PHASE_ON_SITE_DIAGNOSIS, ChargeCodes::TYPE_DIAGNOSIS_FEE);
                }
                return $itens;

            case ChargeCodes::SITUATION_PLATFORM_FAILURE_AFTER_ARRIVAL:
                // Pagamento garantido da etapa comprovadamente executada — a culpa
                // é da plataforma, então o que já foi feito (conforme evidências/
                // contexto) é elegível diretamente, sem passar por revisão.
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
                // Informativo: não gera itens próprios. O motoqueiro recebe pela
                // situação que efetivamente ocorreu na fase dele (normalmente
                // TOWING_RECOMMENDED_ACCEPTED); o guincheiro recebe a etapa de
                // reboque separadamente. Chamar esta situação diretamente é erro
                // de uso — sinaliza para o chamador resolver a situação real do
                // motoqueiro primeiro.
                throw new \LogicException(
                    'OTHER_PROVIDER_EXECUTES_TOWING não gera itens diretamente — resolva a situação real ' .
                    'do primeiro-respondente (ex.: TOWING_RECOMMENDED_ACCEPTED) e trate o reboque como pedido/fase separada.'
                );

            default:
                // Inalcançável — guard-clause no topo já validou contra ChargeCodes::SITUATIONS.
                throw new \LogicException("Situação sem regra implementada: {$situationCode}");
        }
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
