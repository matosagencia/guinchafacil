<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Pricing/ZonePricingService.php
// ROADMAP socorro automotivo — Etapa 13 (preço governado por zona/cidade).
//
// Primeira camada REAL a ler pricing_zones/service_price_rules (schema já
// existia desde install/migration_pricing_zones_v1.sql, mas nenhum
// controller/serviço de produção lia essas tabelas até agora — usuário
// pediu explicitamente para construir isso em 26/07/2026).
//
// Comportamento: dado um ponto (lat, lng), resolve a zona de precificação
// ativa cujo polígono contém esse ponto (ray-casting sobre GeoJSON Polygon)
// e, se essa zona tiver uma regra de preço vigente para o tipo de serviço
// (+ categoria de veículo, quando aplicável), calcula o preço a partir
// dela. Se NENHUMA zona/regra casar, devolve null — quem chama deve cair
// no cálculo global já em produção (TarifaService para reboque,
// ServicePricingRule para os demais serviços). Isto é aditivo por
// desenho: enquanto nenhuma zona tiver polígono desenhado + regra ativa, o
// comportamento do sistema não muda em nada.

require_once __DIR__ . '/../../Models/Pricing/PricingZone.php';
require_once __DIR__ . '/../../Models/Pricing/ServicePriceRule.php';
require_once __DIR__ . '/../../Models/Feriado.php';

final class ZonePricingService
{
    /**
     * Zona ativa cujo polígono contém (lat, lng), ou null se nenhuma
     * zona casar (sem zonas cadastradas, sem polígono desenhado, ou ponto
     * fora de todos os polígonos existentes). Quando mais de uma zona se
     * sobrepõe (não deveria acontecer numa operação bem cadastrada, mas
     * não é validado no cadastro), devolve a primeira encontrada — ordem
     * não é garantida além de "listarAtivas() ordena por nome".
     */
    public static function resolverZonaPorCoordenada(float $lat, float $lng): ?array
    {
        foreach (PricingZone::listarAtivas() as $zona) {
            $poligono = self::extrairAnelExterno((string)($zona['polygon_geojson'] ?? ''));
            if ($poligono === null) {
                continue; // zona sem polígono desenhado — nunca casa (ver doc da classe).
            }
            if (self::pontoDentroDoPoligono($lat, $lng, $poligono)) {
                return $zona;
            }
        }
        return null;
    }

    /**
     * Calcula o preço usando a regra de zona vigente, se existir.
     *
     * @return array{valor:float,zona_id:int,zona_nome:string,regra_id:int,distancia_km:float,detalhe:array}|null
     *   null quando não há zona/regra aplicável — o chamador deve cair no
     *   cálculo global de sempre (TarifaService/ServicePricingRule).
     */
    public static function calcularPreco(
        float $lat,
        float $lng,
        int $serviceTypeId,
        ?string $vehicleCategory,
        float $distanciaKm,
        int $minutos = 0,
        ?DateTimeInterface $dataHora = null
    ): ?array {
        $zona = self::resolverZonaPorCoordenada($lat, $lng);
        if ($zona === null) {
            return null;
        }

        // Tenta a regra específica da categoria do veículo primeiro; cai
        // para a regra "coringa" (vehicle_category NULL, vale para
        // qualquer categoria) quando não houver uma específica.
        $regra = ServicePriceRule::buscarVigente((int)$zona['id'], $serviceTypeId, $vehicleCategory);
        if ($regra === null && $vehicleCategory !== null) {
            $regra = ServicePriceRule::buscarVigente((int)$zona['id'], $serviceTypeId, null);
        }
        if ($regra === null) {
            return null;
        }

        $dataHora = $dataHora ?? new DateTimeImmutable();
        $isNoturno = self::isHorarioNoturno($dataHora);
        $isFeriado = self::isFeriado($dataHora);

        $distanciaKm = max(0.0, round($distanciaKm, 2));
        $distanciaIncluida = (float)($regra['included_distance_km'] ?? 0);
        $distanciaExcedente = max(0.0, $distanciaKm - $distanciaIncluida);
        $precoExcedenteKm = (float)($regra['extra_distance_price'] ?? 0);

        $minutosIncluidos = (int)($regra['included_minutes'] ?? 0);
        $minutosExcedentes = max(0, $minutos - $minutosIncluidos);
        $precoExcedenteMinuto = (float)($regra['extra_minute_price'] ?? 0);

        $base = (float)($regra['base_customer_price'] ?? 0)
            + ($distanciaExcedente * $precoExcedenteKm)
            + ($minutosExcedentes * $precoExcedenteMinuto);

        $multiplicador = 1.0;
        if ($isNoturno) {
            $multiplicador *= (float)($regra['night_multiplier'] ?? 1.0);
        }
        if ($isFeriado) {
            $multiplicador *= (float)($regra['holiday_multiplier'] ?? 1.0);
        }

        $valor = round($base * $multiplicador, 2);

        $minimo = (float)($regra['minimum_customer_price'] ?? 0);
        if ($minimo > 0 && $valor < $minimo) {
            $valor = $minimo;
        }
        $maximo = $regra['maximum_customer_price'] !== null ? (float)$regra['maximum_customer_price'] : null;
        if ($maximo !== null && $maximo > 0 && $valor > $maximo) {
            $valor = $maximo;
        }

        return [
            'valor' => $valor,
            'zona_id' => (int)$zona['id'],
            'zona_nome' => (string)$zona['name'],
            'regra_id' => (int)$regra['id'],
            'distancia_km' => $distanciaKm,
            'detalhe' => [
                'base_customer_price' => (float)($regra['base_customer_price'] ?? 0),
                'distancia_incluida_km' => $distanciaIncluida,
                'distancia_excedente_km' => $distanciaExcedente,
                'preco_excedente_km' => $precoExcedenteKm,
                'minutos_incluidos' => $minutosIncluidos,
                'minutos_excedentes' => $minutosExcedentes,
                'preco_excedente_minuto' => $precoExcedenteMinuto,
                'noturno' => $isNoturno,
                'feriado' => $isFeriado,
                'multiplicador_aplicado' => $multiplicador,
                'minimo' => $minimo,
                'maximo' => $maximo,
                'platform_fee_type' => (string)($regra['platform_fee_type'] ?? 'PERCENTAGE'),
                'platform_fee_value' => (float)($regra['platform_fee_value'] ?? 0),
                'provider_base_amount' => (float)($regra['provider_base_amount'] ?? 0),
            ],
        ];
    }

    /**
     * Ray-casting padrão (algoritmo do número de cruzamentos) sobre o anel
     * externo de um GeoJSON Polygon. $poligono é uma lista de pares
     * [lng, lat] (ordem GeoJSON — cuidado, é lng primeiro, não lat).
     * Não trata buracos (rings internos) — não são um caso de uso real
     * aqui (zonas de cidade não têm "buraco" no meio).
     */
    private static function pontoDentroDoPoligono(float $lat, float $lng, array $poligono): bool
    {
        $dentro = false;
        $n = count($poligono);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float)($poligono[$i][0] ?? 0);
            $yi = (float)($poligono[$i][1] ?? 0);
            $xj = (float)($poligono[$j][0] ?? 0);
            $yj = (float)($poligono[$j][1] ?? 0);

            $intersecta = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersecta) {
                $dentro = !$dentro;
            }
        }
        return $dentro;
    }

    private static function extrairAnelExterno(string $geojsonRaw): ?array
    {
        if (trim($geojsonRaw) === '') {
            return null;
        }
        $decoded = json_decode($geojsonRaw, true);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'Polygon') {
            return null;
        }
        $anel = $decoded['coordinates'][0] ?? null;
        if (!is_array($anel) || count($anel) < 3) {
            return null;
        }
        return $anel;
    }

    private static function isHorarioNoturno(DateTimeInterface $dataHora): bool
    {
        require_once __DIR__ . '/../../Models/Configuracao.php';
        $cfg = Configuracao::getAll();
        $hora = (int)$dataHora->format('H');
        $inicioNoturno = (int)explode(':', (string)($cfg['turno_noturno_inicio'] ?? '20:00'))[0];
        $fimNoturno = (int)explode(':', (string)($cfg['turno_noturno_fim'] ?? '06:00'))[0];

        if ($inicioNoturno === $fimNoturno) {
            return false;
        }
        if ($inicioNoturno < $fimNoturno) {
            return $hora >= $inicioNoturno && $hora < $fimNoturno;
        }
        return $hora >= $inicioNoturno || $hora < $fimNoturno;
    }

    private static function isFeriado(DateTimeInterface $dataHora): bool
    {
        $dataAlvo = $dataHora->format('Y-m-d');
        $mesDiaAlvo = $dataHora->format('m-d');
        foreach (Feriado::listarAtivos() as $feriado) {
            $dataFeriado = substr((string)$feriado['data'], 0, 10);
            if (!empty($feriado['recorrente_anual'])) {
                if (substr($dataFeriado, 5, 5) === $mesDiaAlvo) {
                    return true;
                }
            } elseif ($dataFeriado === $dataAlvo) {
                return true;
            }
        }
        return false;
    }
}
