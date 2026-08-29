<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Pricing/PricingZone.php
// ROADMAP socorro automotivo — Etapa 13 (preço governado por zona).
// Schema/models apenas — nenhum controller de produção lê estas tabelas
// ainda. service_pricing_rules (Etapa 9) continua sendo o fallback global
// em uso até que uma tela admin passe a gerenciar zonas de fato.

final class PricingZone
{
    private const TBL = 'pricing_zones';

    public static function listarAtivas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE active = 1 ORDER BY (ordem_expansao IS NULL) ASC, ordem_expansao ASC, name ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Admin precisa ver zonas inativas também (pra poder reativar). */
    public static function listarTodas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY (ordem_expansao IS NULL) ASC, ordem_expansao ASC, name ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorCodigo(string $code): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * §CELULAS-NITEROI-01 (04/08/2026): zonas ordenadas pela fase de
     * expansão territorial (ordem_expansao), pra alimentar tanto a lista
     * admin (inclui inativas, pra poder reativar) quanto o futuro painel de
     * gates/marcos por célula ($somenteAtivas = true). Zonas sem ordem
     * definida (legado, sem plano de expansão) vão pro fim da lista.
     */
    public static function listarPorOrdemExpansao(?int $cidadeId = null, bool $somenteAtivas = false): array
    {
        $sql = "SELECT * FROM " . self::TBL;
        $where = [];
        $params = [];
        if ($somenteAtivas) {
            $where[] = "active = 1";
        }
        if ($cidadeId !== null) {
            $where[] = "cidade_id = ?";
            $params[] = $cidadeId;
        }
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY (ordem_expansao IS NULL) ASC, ordem_expansao ASC, name ASC";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza só os campos de governança de expansão (ordem, status,
     * bairros de referência) — separado de atualizar() pra não obrigar
     * quem só quer mexer no polígono/nome a mandar esses três campos.
     */
    public static function atualizarExpansao(int $id, ?int $ordemExpansao, string $statusExpansao, ?string $bairrosReferencia): bool
    {
        $statusValido = in_array($statusExpansao, ['nao_ativada', 'pedra_morta', 'pedra_viva'], true) ? $statusExpansao : 'nao_ativada';
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET ordem_expansao = ?, status_expansao = ?, bairros_referencia = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$ordemExpansao, $statusValido, $bairrosReferencia, $id]);
    }

    /**
     * Idempotente por UNIQUE(code) — reenvio não duplica a zona.
     * $polygonGeojson é opcional (parâmetro novo, no fim — não quebra
     * nenhuma chamada existente com a assinatura antiga de 3 argumentos):
     * sem polígono, a zona existe mas nunca "casa" com nenhuma coordenada
     * (ver ZonePricingService::resolverZonaPorCoordenada).
     */
    /**
     * $cidadeId (novo, opcional, no fim — não quebra chamadas existentes de
     * 4 argumentos): tag organizacional pra qual cidade-alvo (tabela
     * `cidades`) esta zona pertence. Só afeta a exibição/gestão no admin —
     * o point-in-polygon de ZonePricingService continua sendo a fonte real
     * da geografia, independente deste campo.
     */
    public static function criar(string $code, string $name, ?string $cityId = null, ?string $polygonGeojson = null, ?int $cidadeId = null): int
    {
        $pdo = getPDO();
        $poligono = self::normalizarGeojson($polygonGeojson);
        // ON DUPLICATE KEY UPDATE é sintaxe MySQL; sob o SQLite usado pelos
        // testes de integração (tests/bootstrap.php) isso quebrava com
        // "syntax error" (mesmo problema já resolvido em Pedido::criar para
        // DATE_ADD/INTERVAL) — faz o upsert manualmente quando o driver é sqlite.
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $existente = self::buscarPorCodigo($code);
            if ($existente) {
                $pdo->prepare("UPDATE " . self::TBL . " SET name = ?, polygon_geojson = ?, cidade_id = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$name, $poligono, $cidadeId, (int)$existente['id']]);
                return (int)$existente['id'];
            }
            $pdo->prepare(
                "INSERT INTO " . self::TBL . " (city_id, cidade_id, code, name, polygon_geojson, active, created_at, updated_at)
                 VALUES (?,?,?,?,?,1,NOW(),NOW())"
            )->execute([$cityId, $cidadeId, $code, $name, $poligono]);
            return (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . " (city_id, cidade_id, code, name, polygon_geojson, active, created_at, updated_at)
             VALUES (?,?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), polygon_geojson = VALUES(polygon_geojson), cidade_id = VALUES(cidade_id), updated_at = NOW()"
        );
        $stmt->execute([$cityId, $cidadeId, $code, $name, $poligono]);
        $existing = self::buscarPorCodigo($code);
        return (int)($existing['id'] ?? 0);
    }

    /**
     * §CELULAS-NITEROI-01 (04/08/2026): metas de oferta/demanda/margem do
     * piloto de 90 dias para esta célula — usado pelo painel ao vivo em
     * TerritorioMetasService. Todos os campos são opcionais (null = "meta
     * ainda não definida para esta célula", célula ainda não ativada).
     */
    public static function atualizarMetas(int $id, array $metas): bool
    {
        $campos = [
            'meta_guinchos_min', 'meta_especialistas_min',
            'meta_prestadores_min', 'meta_prestadores_max', 'meta_disponibilidade_simultanea',
            'meta_atendimentos_mes1', 'meta_atendimentos_mes2', 'meta_atendimentos_mes3',
            'meta_margem_operacional_min_pct', 'meta_margem_pos_marketing_min_pct',
            'meta_composicao_prestadores', 'meta_ciclo_inicio',
        ];
        $sets = [];
        $params = [];
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $metas)) {
                continue;
            }
            $sets[] = "{$campo} = ?";
            $valor = $metas[$campo];
            $params[] = ($valor === '' ? null : $valor);
        }
        if (!$sets) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    /**
     * §CELULAS-NITEROI-01 (04/08/2026): atualiza SÓ a geometria da zona —
     * usado por tools/aplicar_poligonos_celulas_niteroi.php para gravar os
     * polígonos calculados sem tocar em name/active/cidade_id/ordem_expansao/
     * status_expansao/bairros_referencia, que podem já ter sido editados
     * manualmente pelo admin e não podem ser sobrescritos por engano.
     */
    public static function atualizarPoligono(int $id, string $polygonGeojson): bool
    {
        $normalizado = self::normalizarGeojson($polygonGeojson);
        if ($normalizado === null) {
            return false;
        }
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET polygon_geojson = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$normalizado, $id]);
    }

    public static function atualizar(int $id, string $name, bool $active, ?string $polygonGeojson, ?int $cidadeId = null): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET name = ?, active = ?, polygon_geojson = ?, cidade_id = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$name, $active ? 1 : 0, self::normalizarGeojson($polygonGeojson), $cidadeId, $id]);
    }


    /** Valida que o texto colado é um GeoJSON Polygon minimamente bem formado; devolve null se vazio/inválido (nunca lança). */
    public static function normalizarGeojson(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'Polygon' || empty($decoded['coordinates'][0]) || !is_array($decoded['coordinates'][0])) {
            return null;
        }
        return $raw;
    }
}
