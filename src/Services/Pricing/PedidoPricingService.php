<?php

declare(strict_types=1);

require_once __DIR__ . '/../TarifaService.php';
require_once __DIR__ . '/../Financial/ChargePolicyService.php';
require_once __DIR__ . '/../Pedido/ModalidadeResolver.php';

final class PedidoPricingService
{
    public function cotar(array|PedidoQuoteRequest $request): array
    {
        if ($request instanceof PedidoQuoteRequest) {
            throw new InvalidArgumentException('PedidoPricingService exige dados oficiais derivados pelo PedidoCoreService.');
        }

        $data = $request;
        foreach (['distancia_km_oficial', 'modalidade_resolvida'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new InvalidArgumentException('Cotacao sem dados oficiais de precificacao.');
            }
        }

        $distanciaKm = max(0.0, (float)($data['distancia_km_oficial'] ?? 0));
        $categoria = $data['categoria_veiculo_oficial'] ?? null;
        $prioridade = !empty($data['prioridade_oficial']);
        $cidadeId = $data['cidade_id_oficial'] ?? null;
        $modalidade = (string)($data['modalidade_resolvida'] ?? ModalidadeResolver::REBOQUE_PRANCHA);

        $tarifa = TarifaService::calcularDetalhado($distanciaKm, $categoria, $prioridade, null, $cidadeId !== null ? (int)$cidadeId : null);
        $taxaSaida = (float)($tarifa['taxa_fixa_aplicada'] ?? 0);
        $valorKm = (float)($tarifa['tarifa_km_aplicada'] ?? 0);
        $frete = (float)$tarifa['valor'];
        $intermediacao = $modalidade === ModalidadeResolver::SOCORRO_LOCAL
            ? ChargePolicyService::getDirectRescueFee()
            : ChargePolicyService::getTowingIntermediationFee();
        $desconto = !empty($data['continuidade'])
            ? ChargePolicyService::calculateContinuityDiscount($intermediacao)
            : 0.0;
        $intermediacaoFinal = round(max(0.0, $intermediacao - $desconto), 2);

        return [
            'modalidade_socorro' => $modalidade,
            'taxa_saida' => round($taxaSaida, 2),
            'valor_km' => round($valorKm, 2),
            'km_cobrado' => round($distanciaKm, 2),
            'km_excedente' => 0.0,
            'frete' => round($frete, 2),
            'intermediacao' => round($intermediacao, 2),
            'desconto_continuidade' => round($desconto, 2),
            'intermediacao_final' => $intermediacaoFinal,
            'total' => round($frete + $intermediacaoFinal, 2),
            'moeda' => 'BRL',
            'pricing_version' => 'pedido-pricing-v1',
            'tarifa' => $tarifa,
        ];
    }
}
