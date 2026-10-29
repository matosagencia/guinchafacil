<?php
declare(strict_types=1);

require_once __DIR__ . '/CotacaoUnificadaService.php';
require_once __DIR__ . '/../Pedido/ModalidadeResolver.php';

/** Mantém a resposta legada do PedidoCoreService; não calcula tarifas. */
final class PedidoPricingService
{
    public function cotar(array|PedidoQuoteRequest $request): array
    {
        if ($request instanceof PedidoQuoteRequest) {
            throw new InvalidArgumentException('PRICE-UNOFFICIAL-CONTEXT');
        }
        $q = CotacaoUnificadaService::calcular($request);
        if (!($q['ok'] ?? false)) {
            throw new RuntimeException((string)($q['erro'] ?? 'PRICE-CALCULATION-FAILED'));
        }
        $det = (array)$q['detalhamento'];
        $modo = ($q['attendance_mode'] === 'TOWING')
            ? ModalidadeResolver::REBOQUE_PRANCHA : ModalidadeResolver::SOCORRO_LOCAL;
        $total = (float)$q['preco_cliente'];
        return [
            'modalidade_socorro' => $modo,
            'taxa_saida' => (float)$det['taxa_saida'],
            'valor_km' => (float)$det['tarifa_km'],
            'km_cobrado' => (float)$det['km_cobrado'],
            'km_excedente' => (float)$det['km_cobrado'],
            'frete' => $total,
            'intermediacao' => 0.0, // D-14: comissão é divisão interna, não sobretaxa
            'desconto_continuidade' => 0.0, // desconto requer regra versionada própria
            'intermediacao_final' => 0.0,
            'total' => $total,
            'moeda' => 'BRL',
            'pricing_version' => (string)$q['regra_version'],
            'regra_id' => (int)$q['regra_id'],
            'regra_version' => (string)$q['regra_version'],
            'pricing_zone_id' => $q['pricing_zone_id'],
            'service_code' => $q['service_code'],
            'service_type_id' => $q['service_type_id'],
            'tarifa' => $det, // compatibilidade somente-leitura
            'oficial' => $q, // metadados imutáveis para snapshot
        ];
    }
}
