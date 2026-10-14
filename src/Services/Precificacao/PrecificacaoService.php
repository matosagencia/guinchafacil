<?php
declare(strict_types=1);

require_once __DIR__ . '/../Routing/RoutingService.php';
require_once __DIR__ . '/../GuinchoDisponibilidade.php';
require_once __DIR__ . '/../Relevo/RelevoService.php';
require_once __DIR__ . '/../../Models/Configuracao.php';

/**
 * §5 — Motor de preço oficial.
 *
 * Fórmula:
 *   preco = taxa_saida
 *         + (km × tarifa × fator_veiculo × fator_periodo × fator_elevacao)
 *         + deadhead (do anel escolhido, §7)
 *         + pedagio
 *
 * Fontes:
 *   - service_price_rules: taxa_saida, tarifa_km, fatores, versionamento
 *   - configuracoes: fallbacks e multiplicadores globais
 *   - GuinchoDisponibilidade: deadhead do anel
 *   - RelevoService: fator de elevação
 *
 * Não cria pedido, não altera estado. Só calcula e grava snapshot (se pedidoId informado).
 */
final class PrecificacaoService
{
    /**
     * @param array $params {
     *   lat_origem: float,
     *   lng_origem: float,
     *   lat_destino: ?float,
     *   lng_destino: ?float,
     *   service_type_id: int,
     *   vehicle_category: ?string,
     *   pricing_zone_id: ?int,        // auto-detectado se nulo
     *   km_transporte: ?float,         // auto-calculado se nulo
     *   pedagio: ?float,
     *   quando: ?string,               // datetime, default NOW
     *   pedido_id: ?int,               // se informado, grava snapshot
     *   uf_destino: ?string,
     * }
     * @return array {
     *   ok: bool,
     *   erro: ?string,
     *   preco_cliente: float,
     *   detalhamento: array,
     *   snapshot_hash: ?string,
     *   regra_id: ?int,
     *   regra_version: ?string,
     *   anel_usado: ?array,
     * }
     */
    public static function calcular(array $params): array
    {
        $vazio = ['ok' => false, 'erro' => null, 'preco_cliente' => 0.0,
                  'detalhamento' => [], 'snapshot_hash' => null,
                  'regra_id' => null, 'regra_version' => null,
                  'anel_usado' => null];

        // 1. Validações mínimas
        $latO = (float)($params['lat_origem'] ?? 0);
        $lngO = (float)($params['lng_origem'] ?? 0);
        if ($latO === 0.0 || $lngO === 0.0) {
            return array_merge($vazio, ['erro' => 'Coordenadas de origem ausentes.']);
        }
        $serviceTypeId = (int)($params['service_type_id'] ?? 0);
        if ($serviceTypeId <= 0) {
            return array_merge($vazio, ['erro' => 'service_type_id ausente.']);
        }

        try {
            $cfg = Configuracao::getAll();

            // 2. Km de transporte
            $km = isset($params['km_transporte']) && $params['km_transporte'] !== null
                ? (float)$params['km_transporte']
                : null;
            $fonteKm = 'informado';
            if ($km === null) {
                $latD = isset($params['lat_destino']) ? (float)$params['lat_destino'] : null;
                $lngD = isset($params['lng_destino']) ? (float)$params['lng_destino'] : null;
                if ($latD && $lngD) {
                    // Tenta OSRM (km rodoviário real); fallback Haversine
                    $kmRodoviario = RoutingService::distanciaKm($latO, $lngO, $latD, $lngD);
                    if ($kmRodoviario !== null && $kmRodoviario > 0) {
                        $km = $kmRodoviario;
                        $fonteKm = 'osrm';
                    } else {
                        $km = GeoService::haversine($latO, $lngO, $latD, $lngD);
                        $fonteKm = 'haversine';
                    }
                } else {
                    $km = 0.0; // ON_SITE sem destino
                    $fonteKm = 'sem_destino';
                }
            }

            // 3. Regra de preço (service_price_rules)
            $regra = self::buscarRegra($serviceTypeId, (int)($params['pricing_zone_id'] ?? 0), $params['vehicle_category'] ?? null);

            $taxaSaida   = (float)($regra['base_customer_price'] ?? ($cfg['taxa_fixa'] ?? 200.00));
            $tarifaKm    = (float)($regra['extra_distance_price'] ?? ($cfg['tarifa_por_km'] ?? 6.50));
            $kmIncluso   = (float)($regra['included_distance_km'] ?? 0);
            $piso        = (float)($regra['minimum_customer_price'] ?? 0);
            $teto        = (float)($regra['maximum_customer_price'] ?? 0);

            // 4. Fatores
            $fatorVeiculo = self::fatorVeiculo($params['vehicle_category'] ?? null, $regra);
            $fatorPeriodo = self::fatorPeriodo($params['quando'] ?? null, $regra);

            // 5. Relevo (ganho de elevação)
            $relevo = RelevoService::calcularRota(
                $latO, $lngO,
                isset($params['lat_destino']) ? (float)$params['lat_destino'] : null,
                isset($params['lng_destino']) ? (float)$params['lng_destino'] : null
            );
            $fatorElevacao = (float)$relevo['fator_elevacao'];

            // 6. Deadhead do anel (§7)
            $anelUsado = null;
            $deadhead = 0.0;
            if ((string)($params['attendance_mode'] ?? 'TOWING') === 'TOWING') {
                $aneis = GuinchoDisponibilidade::candidatosPorAnel($latO, $lngO, $serviceTypeId);
                $escolhido = GuinchoDisponibilidade::escolherAnel($aneis);
                if ($escolhido !== null) {
                    $anelUsado = [
                        'min_km'    => $escolhido['min_km'],
                        'max_km'    => $escolhido['max_km'],
                        'centro_km' => $escolhido['centro_km'],
                        'elegiveis' => $escolhido['elegiveis'],
                    ];
                    $deadhead = (float)$escolhido['centro_km'] * $tarifaKm;
                }
                // Se nenhum anel elegível, deadhead fica 0
                // (o consumidor decide se cai em fallback WhatsApp)
            }

            // 7. Cálculo — §5.7: pedágio FORA da base da comissão.
            $kmCobrado = max(0.0, $km - $kmIncluso);
            $blocoVariavel = $kmCobrado * $tarifaKm * $fatorVeiculo * $fatorPeriodo * $fatorElevacao;
            $pedagio = (float)($params['pedagio'] ?? 0);

            $baseComissao = $taxaSaida + $blocoVariavel + $deadhead;
            $preco = $baseComissao + $pedagio;

            if ($piso > 0 && $preco < $piso)  $preco = $piso;
            if ($teto > 0 && $preco > $teto) $preco = $teto;

            $detalhamento = [
                'taxa_saida'         => round($taxaSaida, 2),
                'fonte_km'           => $fonteKm,   // <-- ADICIONAR
                'km_total'           => round($km, 2),
                'km_incluso'         => round($kmIncluso, 2),
                'km_cobrado'         => round($kmCobrado, 2),
                'tarifa_km'          => round($tarifaKm, 2),
                'fator_veiculo'      => round($fatorVeiculo, 3),
                'fator_periodo'      => round($fatorPeriodo, 3),
                'fator_elevacao'     => round($fatorElevacao, 3),
                'ganho_elevacao_m'   => $relevo['ganho_elevacao_m'],
                'fonte_relevo'       => $relevo['fonte'],
                'bloco_variavel'     => round($blocoVariavel, 2),
                'deadhead'           => round($deadhead, 2),
                'anel_usado'         => $anelUsado,
                'base_comissao'      => round($baseComissao, 2),
                'pedagio'            => round($pedagio, 2),
                'preco_cliente'      => round($preco, 2),
                'piso_aplicado'      => ($piso > 0 && $preco === (float)$piso),
                'teto_aplicado'      => ($teto > 0 && $preco === (float)$teto),
                'base_km_contagem'   => (string)($regra['base_km_contagem'] ?? 'transporte'),
            ];

            // 8. Snapshot (se pedido informado)
            $snapshotHash = null;
            if (!empty($params['pedido_id'])) {
                $snapshotHash = self::gravarSnapshot((int)$params['pedido_id'], $preco, $detalhamento, $regra);
            }

            return [
                'ok'             => true,
                'erro'           => null,
                'preco_cliente'  => round($preco, 2),
                'detalhamento'   => $detalhamento,
                'snapshot_hash'  => $snapshotHash,
                'regra_id'       => isset($regra['id']) ? (int)$regra['id'] : null,
                'regra_version'  => isset($regra['version']) ? (string)$regra['version'] : null,
                'anel_usado'     => $anelUsado,
            ];
        } catch (Throwable $e) {
            error_log('[PrecificacaoService] ' . $e->getMessage());
            return array_merge($vazio, ['erro' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    private static function buscarRegra(int $serviceTypeId, int $zoneId, ?string $vehicleCategory): array
    {
        try {
            $pdo = getPDO();
            $categoria = self::normalizarCategoria($vehicleCategory);

            // Estratégia em camadas: da mais específica para a mais genérica.
            $tentativas = [
                // 1. Zona + categoria exata
                ['zona' => $zoneId, 'cat' => $categoria, 'soNull' => false],
                // 2. Zona + categoria NULL (regra genérica da zona)
                ['zona' => $zoneId, 'cat' => null,      'soNull' => true],
                // 3. Sem zona + categoria exata
                ['zona' => 0,      'cat' => $categoria, 'soNull' => false],
                // 4. Sem zona + categoria NULL
                ['zona' => 0,      'cat' => null,      'soNull' => true],
                // 5. Sem zona, sem categoria — qualquer ativa (último recurso)
                ['zona' => 0,      'cat' => null,      'soNull' => null],
            ];

            foreach ($tentativas as $t) {
                $sql = "SELECT * FROM service_price_rules
                         WHERE service_type_id = ?
                           AND active = 1
                           AND (effective_from IS NULL OR effective_from <= CURDATE())
                           AND (effective_until IS NULL OR effective_until > CURDATE())";
                $params = [$serviceTypeId];

                if ($t['zona'] > 0) {
                    $sql .= " AND pricing_zone_id = ?";
                    $params[] = $t['zona'];
                }
                if ($t['soNull'] === true) {
                    $sql .= " AND vehicle_category IS NULL";
                } elseif ($t['soNull'] === false) {
                    $sql .= " AND vehicle_category = ?";
                    $params[] = $t['cat'];
                }
                // soNull === null → sem filtro de categoria

                $sql .= " ORDER BY version DESC LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $regra = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($regra) return $regra;
            }

            return [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Mapeia nomes comuns para os valores esperados em vehicle_category.
     * O banco usa 'popular', 'suv', etc.; o funil pode passar 'carro', 'sedan'.
     */
    private static function normalizarCategoria(?string $categoria): ?string
    {
        if ($categoria === null || trim($categoria) === '') return null;
        return match (strtolower(trim($categoria))) {
            'carro', 'sedan', 'hatch', 'passeio', 'popular' => 'popular',
            'suv'                                            => 'suv',
            'utilitario', 'van', 'caminhonete', 'picape'    => 'utilitario',
            'moto', 'motocicleta'                            => 'moto',
            default                                          => strtolower(trim($categoria)),
        };
    }
    
    
    private static function fatorVeiculo(?string $categoria, array $regra): float
    {
        if ($categoria === null || $categoria === '') return 1.00;
        $fator = (float)($regra['fator_veiculo'] ?? 0);
        if ($fator > 0) return $fator;
        return match (strtolower($categoria)) {
            'moto'      => 0.70,
            'suv'       => 1.15,
            'utilitario','van','caminhonete' => 1.25,
            default     => 1.00,
        };
    }

    private static function fatorPeriodo(?string $quando, array $regra): float
    {
        $ts = $quando !== null ? strtotime($quando) : time();
        if ($ts === false) $ts = time();

        $hora = (int)date('G', $ts);
        $diaSemana = (int)date('N', $ts); // 1=seg, 7=dom

        $fator = 1.00;
        // Noturno 20h–6h
        if ($hora >= 20 || $hora < 6) {
            $fator *= (float)($regra['night_multiplier'] ?? 1.20);
        }
        // Fim de semana
        if ($diaSemana >= 6) {
            $fator *= (float)($regra['holiday_multiplier'] ?? 1.10);
        }
        return $fator;
    }

    private static function gravarSnapshot(int $pedidoId, float $preco, array $detalhamento, array $regra): ?string
    {
        try {
            $pdo = getPDO();
            $json = json_encode($detalhamento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hash = hash('sha256', $json . '|' . $preco);

            // Colunas reais: order_id, pricing_rule_id, pricing_version,
            // calculation_context_json, customer_total, provider_total,
            // platform_fee, snapshot_hash
            $regraId   = isset($regra['id']) ? (int)$regra['id'] : null;
            $versao    = isset($regra['version']) ? (int)$regra['version'] : null;

            // platform_fee: comissão sobre a base sem pedágio (§5.7)
            $cfg = Configuracao::getAll();
            $comissao = (float)($cfg['comissao_plataforma'] ?? 0.08);
            $reserva  = (float)($cfg['reserva_gateway_credito'] ?? 0.0498);
            $baseComissao = (float)($detalhamento['base_comissao'] ?? $preco);
            $valorPlataforma = round($baseComissao * $comissao, 2);
            $valorGateway    = round($preco * $reserva, 2);
            $platformFee     = $valorPlataforma;
            $providerTotal   = round($preco - $valorPlataforma - $valorGateway, 2);

            $stmt = $pdo->prepare(
                "INSERT INTO order_price_snapshots
                    (order_id, pricing_rule_id, pricing_version,
                     calculation_context_json, customer_total, provider_total,
                     platform_fee, snapshot_hash, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $pedidoId,
                $regraId,
                $versao,
                $json,
                $preco,
                $providerTotal,
                $platformFee,
                $hash,
            ]);
            return $hash;
        } catch (Throwable $e) {
            error_log('[PrecificacaoService] snapshot falhou: ' . $e->getMessage());
            return null;
        }
    }
}
