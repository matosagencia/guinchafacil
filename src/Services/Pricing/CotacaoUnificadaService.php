<?php
declare(strict_types=1);

require_once __DIR__ . '/../Precificacao/PrecificacaoService.php';
require_once __DIR__ . '/ZonePricingService.php';
require_once __DIR__ . '/../Routing/RoutingService.php';
require_once __DIR__ . '/../../Models/Catalog/ServiceType.php';

/**
 * B-011 / §23: única porta de aplicação para cotação monetária.
 * Nunca recebe km, zona, preço ou percentual do navegador como autoridade.
 * Simulação sem pedido_id; OrderPriceSnapshotWriter persiste ao criar o pedido.
 */
final class CotacaoUnificadaService
{
    public static function calcular(array $entrada): array
    {
        try {
            $lat = self::coord($entrada['lat_origem'] ?? null, -90, 90);
            $lng = self::coord($entrada['lng_origem'] ?? null, -180, 180);
            if ($lat === null || $lng === null || ($lat === 0.0 && $lng === 0.0)) {
                return self::falha('PRICE-ORIGIN-INVALID');
            }

            // Dois meios de identificar serviço; sempre conferidos no catálogo ativo.
            if (!empty($entrada['public_slug'])) {
                $tipo = ServiceType::buscarPorSlugPublico((string)$entrada['public_slug']);
            } elseif (!empty($entrada['service_type_id'])) {
                $stmt = getPDO()->prepare('SELECT * FROM service_types WHERE id = ? AND active = 1 LIMIT 1');
                $stmt->execute([(int)$entrada['service_type_id']]);
                $tipo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $code = strtoupper(trim((string)($entrada['service_code'] ?? '')));
                $stmt = getPDO()->prepare('SELECT * FROM service_types WHERE code = ? AND active = 1 LIMIT 1');
                $stmt->execute([$code]);
                $tipo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$tipo) return self::falha('PRICE-SERVICE-INACTIVE');

            $modo = (string)($tipo['attendance_mode'] ?? '');
            if (!in_array($modo, ['TOWING', 'ON_SITE'], true)) {
                return self::falha('PRICE-MODE-UNSUPPORTED');
            }
            $latD = self::coord($entrada['lat_destino'] ?? null, -90, 90);
            $lngD = self::coord($entrada['lng_destino'] ?? null, -180, 180);
            $km = 0.0;
            $fonte = 'sem_destino';
            if ($modo === 'TOWING') {
                if ($latD === null || $lngD === null) return self::falha('PRICE-DESTINATION-REQUIRED');
                $kmRota = RoutingService::distanciaKm($lat, $lng, $latD, $lngD);
                if ($kmRota === null || !is_finite($kmRota) || $kmRota <= 0) {
                    return self::falha('PRICE-ROUTE-UNAVAILABLE');
                }
                $km = round($kmRota, 2);
                $fonte = RoutingService::ultimaFonte() ?: 'routing';
            } else {
                // Assistência local não herda destino nem km de um request anterior.
                $latD = null;
                $lngD = null;
            }

            $categoria = strtolower(trim((string)($entrada['vehicle_category'] ?? 'popular')));
            // Somente zonas que cobrem a origem. Heranca explicita na mesma cidade.
            $resolucao = ZonePricingService::resolverZonaTarifariaPorCoordenada(
                $lat, $lng, (int)$tipo['id'], $categoria
            );
            $zonaId = (int)($resolucao['zona_tarifaria_id'] ?? 0);
            if ($zonaId <= 0) return self::falha('PRICE-RULE-MISSING');
            $resultado = PrecificacaoService::calcular([
                'lat_origem' => $lat, 'lng_origem' => $lng,
                'lat_destino' => $latD, 'lng_destino' => $lngD,
                'service_type_id' => (int)$tipo['id'],
                'vehicle_category' => $categoria,
                'attendance_mode' => $modo,
                'pricing_zone_id' => $zonaId,
                'km_transporte' => $km, // calculado aqui, do servidor
                'pedagio' => 0.0, // pedágio somente após integração de fonte comprovada
                'quando' => date('Y-m-d H:i:s'), // nunca data/hora do navegador
            ]);
            if (!($resultado['ok'] ?? false)) {
                return self::falha((string)($resultado['erro'] ?? 'PRICE-CALCULATION-FAILED'));
            }
            $detalhe = (array)($resultado['detalhamento'] ?? []);
            $detalhe['zona_geografica_id'] = (int)$resolucao['zona_geografica_id'];
            $detalhe['zona_tarifaria_id'] = $zonaId;
            $resultado['detalhamento'] = $detalhe;
            $resultado['zona_geografica_id'] = (int)$resolucao['zona_geografica_id'];
            $resultado['service_type_id'] = (int)$tipo['id'];
            $resultado['service_code'] = (string)$tipo['code'];
            $resultado['attendance_mode'] = $modo;
            $resultado['pricing_zone_id'] = $zonaId > 0 ? $zonaId : null;
            $resultado['km_transporte'] = $km;
            $resultado['fonte_km'] = $fonte;
            $resultado['comissao_percentual'] = (float)($detalhe['comissao_percentual'] ?? 0.08);
            $resultado['comissao_valor'] = (float)($detalhe['comissao_valor'] ?? 0.0);
            return $resultado;
        } catch (Throwable $e) {
            error_log('[B011][CotacaoUnificadaService] ' . get_class($e) . ': ' . $e->getMessage());
            return self::falha(preg_match('/^(PRICE-[A-Z0-9-]+)/', $e->getMessage(), $m) ? $m[1] : 'PRICE-INTERNAL');
        }
    }

    private static function coord(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $value = (float)$value;
        return is_finite($value) && $value >= $min && $value <= $max ? $value : null;
    }

    private static function falha(string $erro): array
    {
        return ['ok' => false, 'erro' => $erro, 'preco_cliente' => 0.0,
            'detalhamento' => [], 'regra_id' => null, 'regra_version' => null, 'snapshot_hash' => null];
    }
}
