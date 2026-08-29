<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/TerritorioMetasService.php
// §CELULAS-NITEROI-01 (04/08/2026): painel ao vivo de metas por célula
// territorial, alimentando o Dashboard. Responde, célula a célula, às
// perguntas da estratégia de domínio progressivo de Niterói:
//   quantos prestadores temos vs meta, quantos pedidos pagos vs meta de
//   90 dias, quanto entrou, quanto foi repassado, quanto perdemos em
//   estornos/cancelamentos, e qual a margem operacional real.
// Deliberadamente NÃO cacheia nada — todo número é calculado ao vivo a
// partir de pedidos/pagamentos/payout_ledger_entries. A meta (alvo) é o
// único dado gravado, em pricing_zones (migration v5).
//
// Limitação conhecida (documentada, não escondida): "prestadores
// homologados" é hoje uma aproximação por CIDADE (guinchos.cidade_id),
// não por célula/bairro — guinchos ainda não têm amarração a uma célula
// específica. Pedidos/receita/repasse/margem, esses sim, já são exatos
// por célula, porque pedidos.pricing_zone_id é resolvido por
// point-in-polygon real (ZonePricingService) desde a migration v4.

require_once __DIR__ . '/../Models/Pricing/PricingZone.php';

final class TerritorioMetasService
{
    /**
     * §CELULAS-NITEROI-01 (04/08/2026): mapeamento da composição mínima de
     * oferta do piloto ("4 guinchos leves, 4 moto-socorristas/apoio, 2
     * oficinas parceiras, 2 especialistas bateria/elétrica, 1 pneu, 1
     * reserva operacional") para `service_types.code` reais, via
     * `provider_capabilities` (ver migration_service_catalog_v1.sql).
     * "oficina_parceira" e "reserva_operacional" NÃO têm categoria própria
     * modelada hoje no cadastro de prestador — ficam marcadas como
     * `computavel = false` em vez de fingir um número. Um prestador pode
     * aparecer em mais de um grupo (acumula capacidades), exatamente como
     * a meta do piloto prevê ("não significa necessariamente 15 empresas
     * diferentes").
     */
    /**
     * §COBERTURA-RAIO-01 (05/08/2026): acima desse percentual de pedidos
     * cancelados por timeout de aceite (30 min, ver
     * tools/cron_cancelar_pedidos_expirados.php), a célula NUNCA é sugerida
     * como 'pedra_viva' — mesmo que a composição de oferta e a margem
     * estejam ok no papel. É o sinal mais honesto de que a oferta
     * declarada no cadastro não está de fato convertendo em atendimento:
     * prestador existe, mas ninguém aceita a tempo.
     */
    private const TAXA_TIMEOUT_MAX_PCT = 20.0;

    /** Precisa bater exatamente com a string gravada em cron_cancelar_pedidos_expirados.php. */
    private const MOTIVO_CANCELAMENTO_TIMEOUT = 'Expiração automática do aceite do guincho.';

    private const COMPOSICAO_OFERTA = [
        'guincho_leve' => ['label' => 'Guinchos leves', 'meta' => 4, 'codes' => ['TOW_CAR']],
        'apoio_moto' => ['label' => 'Moto-socorristas / apoio', 'meta' => 4, 'codes' => ['TOW_MOTORCYCLE', 'MECHANICAL_ASSISTANCE']],
        'oficina_parceira' => ['label' => 'Oficinas parceiras', 'meta' => 2, 'codes' => []],
        'eletrica' => ['label' => 'Especialistas bateria/elétrica', 'meta' => 2, 'codes' => ['JUMP_START', 'BATTERY_TEST', 'BATTERY_REPLACEMENT', 'ELECTRICAL_DIAGNOSIS']],
        'pneu' => ['label' => 'Prestador de pneu', 'meta' => 1, 'codes' => ['TIRE_CHANGE', 'TIRE_INFLATION']],
        'reserva_operacional' => ['label' => 'Reserva operacional', 'meta' => 1, 'codes' => []],
    ];

    /**
     * Conta prestadores DISTINTOS (não linhas de capacidade) aprovados e
     * habilitados para qualquer um dos `service_types.code` informados,
     * dentro da cidade da célula (mesma aproximação por cidade já usada
     * para `prestadores_homologados` — documentada como limitação
     * conhecida no topo deste arquivo).
     */
    private static function contarPrestadoresPorCapacidade(\PDO $pdo, int $cidadeId, array $codes): int
    {
        if (!$codes) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = "SELECT COUNT(DISTINCT pc.provider_id)
                FROM provider_capabilities pc
                JOIN service_types st ON st.id = pc.service_type_id
                JOIN guinchos g ON g.id = pc.provider_id
                WHERE g.cidade_id = ?
                  AND pc.approval_status = 'APPROVED'
                  AND pc.enabled = 1
                  AND st.code IN ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$cidadeId], $codes));
        return (int)$stmt->fetchColumn();
    }

    /**
     * Composição de oferta da célula + classificação SUGERIDA (pedra viva
     * / pedra morta) com base na meta de 90 dias. É só uma sugestão —
     * nunca escreve em `pricing_zones.status_expansao` sozinha; o admin
     * continua decidindo/auditando a mudança de status via
     * `PricingZone::atualizarExpansao()`, igual hoje.
     */
    private static function classificarOferta(\PDO $pdo, ?int $cidadeId, ?int $metaPrestadoresMin, ?int $metaGuinchosMin, ?int $metaEspecialistasMin, int $prestadoresHomologados, int $guinchosHomologados, ?int $especialistasHomologados, float $margemOperacional, ?float $taxaTimeoutPct): array
    {
        $itens = [];
        $todosComputaveisAtingidos = true;
        $algumComputavel = false;

        foreach (self::COMPOSICAO_OFERTA as $chave => $def) {
            $computavel = !empty($def['codes']) && $cidadeId !== null;
            $atual = $computavel ? self::contarPrestadoresPorCapacidade($pdo, $cidadeId, $def['codes']) : null;
            if ($computavel) {
                $algumComputavel = true;
                if ($atual < $def['meta']) {
                    $todosComputaveisAtingidos = false;
                }
            }
            $itens[] = [
                'chave' => $chave,
                'label' => $def['label'],
                'meta' => $def['meta'],
                'atual' => $atual,
                'computavel' => $computavel,
                'atingido' => $computavel ? ($atual >= $def['meta']) : null,
            ];
        }

        $timeoutAltoDemais = $taxaTimeoutPct !== null && $taxaTimeoutPct > self::TAXA_TIMEOUT_MAX_PCT;

        $metasSeparadasDefinidas = $metaGuinchosMin !== null && $metaEspecialistasMin !== null;
        $ofertaSeparadaOk = $metasSeparadasDefinidas
            && $guinchosHomologados >= $metaGuinchosMin
            && $especialistasHomologados !== null
            && $especialistasHomologados >= $metaEspecialistasMin;
        if ($metaPrestadoresMin === null || !$metasSeparadasDefinidas) {
            $classificacao = null; // célula sem meta definida ainda — nada a classificar
        } else {
            $ofertaOk = ($prestadoresHomologados >= $metaPrestadoresMin) && $ofertaSeparadaOk && (!$algumComputavel || $todosComputaveisAtingidos);
            $classificacao = ($ofertaOk && $margemOperacional > 0 && !$timeoutAltoDemais) ? 'pedra_viva' : 'pedra_morta';
        }

        return [
            'itens' => $itens,
            'classificacao_sugerida' => $classificacao,
            'possui_item_nao_modelado' => (bool)array_filter($itens, static fn(array $i): bool => !$i['computavel']),
            'timeout_bloqueou_classificacao' => $timeoutAltoDemais,
            'metas_separadas_definidas' => $metasSeparadasDefinidas,
            'oferta_separada_ok' => $ofertaSeparadaOk,
        ];
    }

    /**
     * @return array<int, array<string, mixed>> uma linha por célula, na
     *         ordem de expansão (célula 1 primeiro).
     */
    public static function painel(): array
    {
        $pdo = getPDO();
        $zonas = PricingZone::listarPorOrdemExpansao();
        $resultado = [];

        foreach ($zonas as $z) {
            $zonaId = (int)$z['id'];

            $s = $pdo->prepare(
                "SELECT COUNT(*) pedidos_pagos,
                        COALESCE(SUM(pg.valor_total), 0) bruto,
                        COALESCE(SUM(pg.valor_plataforma), 0) comissao
                 FROM pagamentos pg
                 JOIN pedidos p ON p.id = pg.pedido_id
                 WHERE pg.status = 'aprovado' AND p.pricing_zone_id = ?"
            );
            $s->execute([$zonaId]);
            $fin = $s->fetch(\PDO::FETCH_ASSOC) ?: [];

            $s = $pdo->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM payout_ledger_entries
                 WHERE entry_type = 'debito_repasse_guincho'
                   AND pedido_id IN (SELECT id FROM pedidos WHERE pricing_zone_id = ?)"
            );
            $s->execute([$zonaId]);
            $repassado = (float)$s->fetchColumn();

            $s = $pdo->prepare(
                "SELECT COUNT(*) qtd, COALESCE(SUM(pg.valor_total), 0) valor
                 FROM pagamentos pg
                 JOIN pedidos p ON p.id = pg.pedido_id
                 WHERE pg.status = 'estornado' AND p.pricing_zone_id = ?"
            );
            $s->execute([$zonaId]);
            $estornos = $s->fetch(\PDO::FETCH_ASSOC) ?: [];

            $s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE pricing_zone_id = ? AND status = 'cancelado'");
            $s->execute([$zonaId]);
            $pedidosCancelados = (int)$s->fetchColumn();

            // §COBERTURA-RAIO-01 (05/08/2026): pedidos cancelados especificamente
            // pelo cron de expiração de 30 min (ninguém aceitou a tempo) — sinal
            // de que a oferta declarada na célula não está convertendo em
            // atendimento de verdade, independente do que a composição diz.
            $s = $pdo->prepare(
                "SELECT COUNT(*) FROM pedidos WHERE pricing_zone_id = ? AND status = 'cancelado' AND motivo_cancelamento = ?"
            );
            $s->execute([$zonaId, self::MOTIVO_CANCELAMENTO_TIMEOUT]);
            $pedidosTimeoutCancelados = (int)$s->fetchColumn();

            $s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE pricing_zone_id = ?");
            $s->execute([$zonaId]);
            $totalPedidosZona = (int)$s->fetchColumn();

            $taxaTimeoutPct = $totalPedidosZona > 0
                ? round($pedidosTimeoutCancelados / $totalPedidosZona * 100, 1)
                : null;

            $prestadoresHomologados = null;
            $guinchosHomologados = 0;
            $especialistasHomologados = null;
            if (!empty($z['cidade_id'])) {
                $s = $pdo->prepare('SELECT COUNT(*) FROM guinchos WHERE cidade_id = ? AND aprovado = 1');
                $s->execute([(int)$z['cidade_id']]);
                $prestadoresHomologados = (int)$s->fetchColumn();
                $s = $pdo->prepare('SELECT COUNT(*) FROM guinchos WHERE cidade_id = ? AND aprovado = 1 AND oferece_reboque = 1');
                $s->execute([(int)$z['cidade_id']]);
                $guinchosHomologados = (int)$s->fetchColumn();
                $s = $pdo->prepare("SELECT COUNT(DISTINCT pc.provider_id) FROM provider_capabilities pc JOIN service_types st ON st.id=pc.service_type_id JOIN guinchos g ON g.id=pc.provider_id WHERE g.cidade_id=? AND g.aprovado=1 AND pc.enabled=1 AND pc.approval_status='APPROVED' AND st.code IN ('TOW_MOTORCYCLE','MECHANICAL_ASSISTANCE','JUMP_START','BATTERY_TEST','BATTERY_REPLACEMENT','ELECTRICAL_DIAGNOSIS','TIRE_CHANGE','TIRE_INFLATION')");
                $s->execute([(int)$z['cidade_id']]);
                $especialistasHomologados = (int)$s->fetchColumn();
            }

            $pedidosPagos = (int)($fin['pedidos_pagos'] ?? 0);
            $comissao = (float)($fin['comissao'] ?? 0);
            $perdaEstornos = (float)($estornos['valor'] ?? 0);
            $margemOperacional = round($comissao - $perdaEstornos, 2);

            $metaMes1 = $z['meta_atendimentos_mes1'] !== null ? (int)$z['meta_atendimentos_mes1'] : null;
            $metaMes2 = $z['meta_atendimentos_mes2'] !== null ? (int)$z['meta_atendimentos_mes2'] : null;
            $metaMes3 = $z['meta_atendimentos_mes3'] !== null ? (int)$z['meta_atendimentos_mes3'] : null;
            $meta90d = ($metaMes1 !== null || $metaMes2 !== null || $metaMes3 !== null)
                ? (int)$metaMes1 + (int)$metaMes2 + (int)$metaMes3
                : null;
            $progressoPedidosPct = ($meta90d !== null && $meta90d > 0)
                ? round(min(100, $pedidosPagos / $meta90d * 100), 1)
                : null;

            $metaPrestadoresMin = $z['meta_prestadores_min'] !== null ? (int)$z['meta_prestadores_min'] : null;
            $metaGuinchosMin = $z['meta_guinchos_min'] !== null ? (int)$z['meta_guinchos_min'] : null;
            $metaEspecialistasMin = $z['meta_especialistas_min'] !== null ? (int)$z['meta_especialistas_min'] : null;
            if ($metaPrestadoresMin === null && $metaGuinchosMin !== null && $metaEspecialistasMin !== null) {
                $metaPrestadoresMin = $metaGuinchosMin + $metaEspecialistasMin;
            }
            $progressoPrestadoresPct = ($metaPrestadoresMin !== null && $metaPrestadoresMin > 0 && $prestadoresHomologados !== null)
                ? round(min(100, $prestadoresHomologados / $metaPrestadoresMin * 100), 1)
                : null;

            $cidadeIdZona = !empty($z['cidade_id']) ? (int)$z['cidade_id'] : null;
            $oferta = self::classificarOferta($pdo, $cidadeIdZona, $metaPrestadoresMin, $metaGuinchosMin, $metaEspecialistasMin, (int)($prestadoresHomologados ?? 0), $guinchosHomologados, $especialistasHomologados, $margemOperacional, $taxaTimeoutPct);

            $diasDeCiclo = null;
            $diasRestantesCiclo = null;
            if (!empty($z['meta_ciclo_inicio'])) {
                $inicio = strtotime((string)$z['meta_ciclo_inicio']);
                if ($inicio !== false) {
                    $diasDeCiclo = max(0, (int)floor((time() - $inicio) / 86400));
                    $diasRestantesCiclo = max(0, 90 - $diasDeCiclo);
                }
            }

            $resultado[] = [
                'id' => $zonaId,
                'code' => $z['code'],
                'name' => $z['name'],
                'cidade_id' => !empty($z['cidade_id']) ? (int)$z['cidade_id'] : null,
                'polygon_geojson' => (string)($z['polygon_geojson'] ?? ''),
                'status_expansao' => $z['status_expansao'] ?? 'nao_ativada',
                'ordem_expansao' => $z['ordem_expansao'] !== null ? (int)$z['ordem_expansao'] : null,
                'tem_poligono' => !empty($z['polygon_geojson']),
                'ciclo_dias_decorridos' => $diasDeCiclo,
                'ciclo_dias_restantes' => $diasRestantesCiclo,
                'meta_prestadores_min' => $metaPrestadoresMin,
                'meta_guinchos_min' => $metaGuinchosMin,
                'meta_especialistas_min' => $metaEspecialistasMin,
                'meta_prestadores_max' => $z['meta_prestadores_max'] !== null ? (int)$z['meta_prestadores_max'] : null,
                'meta_disponibilidade_simultanea' => $z['meta_disponibilidade_simultanea'] !== null ? (int)$z['meta_disponibilidade_simultanea'] : null,
                'meta_composicao_prestadores' => $z['meta_composicao_prestadores'] ?? null,
                'meta_atendimentos_mes1' => $metaMes1,
                'meta_atendimentos_mes2' => $metaMes2,
                'meta_atendimentos_mes3' => $metaMes3,
                'meta_atendimentos_90d' => $meta90d,
                'meta_margem_operacional_min_pct' => $z['meta_margem_operacional_min_pct'] !== null ? (float)$z['meta_margem_operacional_min_pct'] : null,
                'prestadores_homologados' => $prestadoresHomologados,
                'guinchos_homologados' => $guinchosHomologados,
                'especialistas_homologados' => $especialistasHomologados,
                'progresso_prestadores_pct' => $progressoPrestadoresPct,
                'pedidos_pagos' => $pedidosPagos,
                'pedidos_cancelados' => $pedidosCancelados,
                'progresso_pedidos_pct' => $progressoPedidosPct,
                'receita_bruta' => (float)($fin['bruto'] ?? 0),
                'repassado_prestadores' => $repassado,
                'comissao_plataforma' => $comissao,
                'perdas_estorno_valor' => $perdaEstornos,
                'perdas_estorno_qtd' => (int)($estornos['qtd'] ?? 0),
                'margem_operacional' => $margemOperacional,
                'margem_operacional_pct' => (float)($fin['bruto'] ?? 0) > 0 ? round($margemOperacional / (float)$fin['bruto'] * 100, 1) : null,
                'oferta_composicao' => $oferta['itens'],
                'oferta_possui_item_nao_modelado' => $oferta['possui_item_nao_modelado'],
                'classificacao_sugerida' => $oferta['classificacao_sugerida'],
                'pedidos_timeout_cancelados' => $pedidosTimeoutCancelados,
                'taxa_timeout_pct' => $taxaTimeoutPct,
                'taxa_timeout_max_pct' => self::TAXA_TIMEOUT_MAX_PCT,
                'timeout_bloqueou_classificacao' => $oferta['timeout_bloqueou_classificacao'],
                'metas_separadas_definidas' => $oferta['metas_separadas_definidas'],
                'oferta_separada_ok' => $oferta['oferta_separada_ok'],
            ];
        }

        return $resultado;
    }
}
