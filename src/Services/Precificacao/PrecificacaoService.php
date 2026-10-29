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
        // §23: TOWING nunca tem distância fictícia ou destino ausente.
        if (($params['attendance_mode'] ?? 'TOWING') === 'TOWING'
            && (!isset($params['lat_destino'], $params['lng_destino']))) {
            return array_merge($vazio, ['erro' => 'PRICE-DESTINATION-REQUIRED']);
        }
        if (isset($params['km_transporte']) &&
            (!is_numeric($params['km_transporte']) || !is_finite((float)$params['km_transporte'])
             || (float)$params['km_transporte'] < 0)) {
            return array_merge($vazio, ['erro' => 'PRICE-DISTANCE-INVALID']);
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
                        $fonteKm = RoutingService::ultimaFonte() ?? 'desconhecida';
                    } else {
                        throw new RuntimeException('PRICE-ROUTE-UNAVAILABLE: distância rodoviária indisponível; linha reta não autorizada.');
                    }
                } else {
                    $km = 0.0; // ON_SITE sem destino
                    $fonteKm = 'sem_destino';
                }
            }

            // 3. Regra de preço (service_price_rules)
            // B-011: reject disabled services and missing official rules.
            $activeStmt = getPDO()->prepare('SELECT id FROM service_types WHERE id = ? AND active = 1 LIMIT 1');
            $activeStmt->execute([$serviceTypeId]);
            if (!$activeStmt->fetchColumn()) return array_merge($vazio, ['erro' => 'PRICE-SERVICE-INACTIVE']);
            $regra = self::buscarRegra($serviceTypeId, (int)($params['pricing_zone_id'] ?? 0), $params['vehicle_category'] ?? null);
            if (!$regra) return array_merge($vazio, ['erro' => 'PRICE-RULE-MISSING']);
            // D-14: comissão única de 8%. Rejeita regra divergente, sem
            // ajustar silenciosamente valores contratuais no banco.
            $feeRaw = $regra['platform_fee_value'] ?? null;
            $feePct = $feeRaw === null ? 0.08 : (float)$feeRaw;
            if ($feePct > 1.0) $feePct /= 100.0;
            if (!is_finite($feePct) || abs($feePct - 0.08) > 0.000001) {
                return array_merge($vazio, ['erro' => 'PRICE-COMMISSION-CONFIG-MISMATCH']);
            }
            if (!is_numeric($regra['base_customer_price'] ?? null)
                || !is_numeric($regra['extra_distance_price'] ?? null)
                || (float)$regra['base_customer_price'] < 0
                || (float)$regra['extra_distance_price'] < 0) {
                return array_merge($vazio, ['erro' => 'PRICE-RULE-INVALID']);
            }

            if (!$regra || !in_array((string)($regra['base_km_contagem'] ?? ''), ['transporte', 'percurso_completo', 'percurso_efetivo'], true)) {
                throw new LogicException('PRICE-RULE-MISSING: regra vigente ou base_km_contagem não configurada.');
            }
            if ($regra['base_km_contagem'] !== 'transporte') {
                throw new LogicException('PRICE-KM-MODE-UNSUPPORTED: contagem de km requer trechos comprovados.');
            }

            $taxaSaida   = (float)($regra['base_customer_price'] ?? ($cfg['taxa_fixa'] ?? 200.00));
            $tarifaKm    = (float)($regra['extra_distance_price'] ?? ($cfg['tarifa_por_km'] ?? 6.50));
            $kmIncluso   = (float)($regra['included_distance_km'] ?? 0);
            $piso        = (float)($regra['minimum_customer_price'] ?? 0);
            $teto        = (float)($regra['maximum_customer_price'] ?? 0);
            if ($taxaSaida < 0 || $tarifaKm < 0 || $kmIncluso < 0 || $piso < 0
                || ($teto > 0 && $teto < $taxaSaida)) {
                return array_merge($vazio, ['erro' => 'PRICE-RULE-INVALID']);
            }

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
            // Fator de terreno independente do relevo; 1.0 é o elemento neutro
            // quando a regra não define acréscimo por terreno.
            $fatorTerreno = isset($regra['fator_terreno']) && $regra['fator_terreno'] !== ''
                ? (float)$regra['fator_terreno'] : 1.0;
            if (!is_finite($fatorTerreno) || $fatorTerreno <= 0 ||
                !is_finite($fatorElevacao) || $fatorElevacao <= 0) {
                throw new LogicException('PRICE-FACTOR-INVALID: terreno/relevo inválido.');
            }

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
            // Componentes calculados ANTES do arredondamento, na mesma ordem da formula vigente.
            $blocoBase = $kmCobrado * $tarifaKm * $fatorVeiculo * $fatorPeriodo;
            $terrenoValor = $blocoBase * ($fatorTerreno - 1.0);
            $elevacaoValor = ($blocoBase * $fatorTerreno) * ($fatorElevacao - 1.0);
            $blocoVariavel = $blocoBase * $fatorTerreno * $fatorElevacao;
            $pedagio = (float)($params['pedagio'] ?? 0);
            if (!is_finite($pedagio) || $pedagio < 0) {
                return array_merge($vazio, ['erro' => 'PRICE-PEDAGIO-INVALID']);
            }

            $baseComissao = $taxaSaida + $blocoVariavel + $deadhead;
            $preco = $baseComissao + $pedagio;

            if ($piso > 0 && $preco < $piso)  $preco = $piso;
            if ($teto > 0 && $preco > $teto) $preco = $teto;
            // Piso/teto não podem transferir pedágio para a base de comissão.
            if ($preco < $pedagio) throw new LogicException('PRICE-CAP-INVALID: teto inferior ao pedágio.');
            $baseComissao = round($preco - $pedagio, 2);
            $comissaoValor = round($baseComissao * 0.08, 2);

            // Conciliacao centesimal; piso/teto entra em componente separado, nunca oculto.
            $componentBase = round($blocoBase, 2);
            $componentTerreno = round($terrenoValor, 2);
            $componentElevacao = round($elevacaoValor, 2);
            $componentDeadhead = round($deadhead, 2);
            $ajustePisoTeto = round(
                round($preco, 2) - round($pedagio, 2) - round($taxaSaida, 2)
                - $componentBase - $componentTerreno - $componentElevacao - $componentDeadhead,
                2
            );

            // Relevo indisponivel nao pode ser representado artificialmente por subida/descida 0.
            $ganhoEstruturado = null;
            if (isset($relevo['subida_m'], $relevo['descida_m'], $relevo['ganho_elevacao_m'])
                && is_numeric($relevo['subida_m']) && is_numeric($relevo['descida_m'])
                && is_numeric($relevo['ganho_elevacao_m'])) {
                $ganhoEstruturado = [
                    'subida' => (float)$relevo['subida_m'],
                    'descida' => (float)$relevo['descida_m'],
                    'efetivo' => (float)$relevo['ganho_elevacao_m'],
                ];
            }

            $detalhamento = [
                'taxa_saida'         => round($taxaSaida, 2),
                'fonte_km'           => $fonteKm,
                'km_total'           => round($km, 2),
                'km_incluso'         => round($kmIncluso, 2),
                'km_cobrado'         => round($kmCobrado, 2),
                'tarifa_km'          => round($tarifaKm, 2),
                'fator_veiculo'      => round($fatorVeiculo, 3),
                'fator_periodo'      => round($fatorPeriodo, 3),
                'fator_elevacao'     => $fatorElevacao,
                'fator_terreno'      => $fatorTerreno,
                'ganho_elevacao_m'   => $ganhoEstruturado,
                'fonte_relevo'       => $relevo['fonte'],
                'bloco_variavel'     => round($blocoVariavel, 2),
                'component_km_base'  => $componentBase,
                'component_elevacao' => $componentElevacao,
                'component_terreno'  => $componentTerreno,
                'component_deadhead' => $componentDeadhead,
                'component_ajuste_piso_teto' => $ajustePisoTeto,
                'terreno_modelo'     => 'multiplicador_legado',
                'deadhead'           => round($deadhead, 2),
                'deadhead_ref'       => $anelUsado === null ? 0.0 : (float)$anelUsado['centro_km'],
                'anel_usado'         => $anelUsado,
                'base_comissao'      => round($baseComissao, 2),
                'comissao_percentual'=> 0.08,
                'comissao_valor'     => $comissaoValor,
                'regra_id'          => (int)$regra['id'],
                'regra_version'     => (string)$regra['version'],
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
            // Erro público estável, mensagem técnica exclusivamente nos logs.
            return array_merge($vazio, ['erro' =>
                preg_match('/^(PRICE-[A-Z0-9-]+)/', $e->getMessage(), $m) ? $m[1] : 'PRICE-INTERNAL']);
        }
    }

    private static function buscarRegra(int $serviceTypeId, int $zoneId, ?string $vehicleCategory): array
    {
        // B-011: explicit zone first; only NULL zones are global.
        $categoria = self::normalizarCategoria($vehicleCategory);
        $tries = [];
        if ($zoneId > 0) {
            if ($categoria !== null) $tries[] = [$zoneId, $categoria];
            $tries[] = [$zoneId, null];
        }
        if ($categoria !== null) $tries[] = [null, $categoria];
        $tries[] = [null, null];
        foreach ($tries as [$zona, $cat]) {
            $sql = 'SELECT * FROM service_price_rules WHERE service_type_id = ? AND active = 1'
                 . ' AND (effective_from IS NULL OR effective_from <= CURDATE())'
                 . ' AND (effective_until IS NULL OR effective_until > CURDATE())';
            $params = [$serviceTypeId];
            if ($zona === null) $sql .= ' AND pricing_zone_id IS NULL';
            else { $sql .= ' AND pricing_zone_id = ?'; $params[] = $zona; }
            if ($cat === null) $sql .= ' AND vehicle_category IS NULL';
            else { $sql .= ' AND vehicle_category = ?'; $params[] = $cat; }
            $sql .= ' ORDER BY version DESC, id DESC LIMIT 1';
            $stmt = getPDO()->prepare($sql);
            $stmt->execute($params);
            $regra = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($regra) return $regra;
        }
        return [];
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
            'moto', 'suv', 'utilitario', 'van', 'caminhonete' => 1.00,
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


            // Colunas reais: order_id, pricing_rule_id, pricing_version,
            // calculation_context_json, customer_total, provider_total,
            // platform_fee, snapshot_hash
            $regraId   = isset($regra['id']) ? (int)$regra['id'] : null;
            $versaoRaw = $regra['version'] ?? null;
            if (!is_scalar($versaoRaw) || !ctype_digit((string)$versaoRaw) || (int)$versaoRaw <= 0) {
                throw new LogicException('PRICE-VERSION-MISSING: versão inteira positiva obrigatória.');
            }
            $versao = (int)$versaoRaw;

            // platform_fee: comissão sobre a base sem pedágio (§5.7)
            $cfg = Configuracao::getAll();
            $comissao = 0.08; // D-14, já validada na seleção da regra
            $reserva  = (float)($cfg['reserva_gateway_credito'] ?? 0.0498);
            $baseComissao = (float)($detalhamento['base_comissao'] ?? $preco);
            $valorPlataforma = round($baseComissao * $comissao, 2);
            $valorGateway    = round($preco * $reserva, 2);
            $platformFee     = $valorPlataforma;
            $providerTotal   = round($preco - $valorPlataforma - $valorGateway, 2);

            // §5.8: snapshot imutável do cálculo efetivamente usado, incluindo
            // comissão e versão. Não reconstruir a partir das regras futuramente.
            $contexto = $detalhamento;
            $contexto['comissao'] = $platformFee;
            $contexto['comissao_percentual'] = 0.08;
            $contexto['valor_total'] = round($preco, 2);
            $contexto['version'] = $versao;
            $obrigatorios = [
                'taxa_saida', 'km_cobrado', 'tarifa_km', 'base_km_contagem',
                'fator_veiculo', 'fator_periodo', 'deadhead_ref', 'fator_terreno',
                'ganho_elevacao_m', 'component_elevacao', 'component_terreno',
                'component_deadhead', 'pedagio', 'valor_total', 'comissao', 'version',
            ];
            foreach ($obrigatorios as $campo) {
                if (!array_key_exists($campo, $contexto) || ($contexto[$campo] === null && $campo !== 'ganho_elevacao_m')) {
                    throw new LogicException('PRICE-SNAPSHOT-INCOMPLETE: ' . $campo);
                }
            }
            $somaComponentes = round(
                (float)$contexto['taxa_saida'] + (float)$contexto['component_km_base']
                + (float)$contexto['component_terreno'] + (float)$contexto['component_elevacao']
                + (float)$contexto['component_deadhead'] + (float)$contexto['component_ajuste_piso_teto']
                + (float)$contexto['pedagio'], 2
            );
            if (abs($somaComponentes - (float)$contexto['valor_total']) > 0.009) {
                throw new LogicException('PRICE-SNAPSHOT-RECONCILIATION-FAILED');
            }
            $json = json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $hash = hash('sha256', $json . '|' . number_format($preco, 2, '.', ''));

            // Não duplicar ou sobrescrever snapshots existentes do mesmo pedido.
            $anterior = $pdo->prepare(
                'SELECT snapshot_hash FROM order_price_snapshots WHERE order_id = ? LIMIT 1'
            );
            $anterior->execute([$pedidoId]);
            $hashAnterior = $anterior->fetchColumn();
            if ($hashAnterior !== false) {
                if (hash_equals((string)$hashAnterior, $hash)) {
                    return $hash;
                }
                throw new LogicException('PRICE-SNAPSHOT-CONFLICT: pedido já possui snapshot diferente.');
            }

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
            throw $e; // fail-closed: não retornar cálculo OK sem snapshot solicitado
        }
    }
}
