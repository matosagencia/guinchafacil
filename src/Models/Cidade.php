<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Cidade.php
// Cidade-alvo — unidade de expansão territorial usada para vincular
// guincheiros a uma cidade de atuação (obrigatório no cadastro) e
// segmentar o planejamento de lançamento por cidade. Cliente nunca é
// vinculado a cidade: pode solicitar de qualquer lugar.

require_once __DIR__ . '/../Services/GeoService.php';

final class Cidade
{
    private const TBL = 'cidades';

    public static function listarTodas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY nome ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function listarAtivas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE ativo = 1 ORDER BY nome ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorSlug(string $slug): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function gerarSlug(string $nome, string $uf): string
    {
        $base = strtolower(trim($nome . '-' . $uf));
        $base = preg_replace('/[^a-z0-9]+/u', '-', self::semAcentos($base)) ?? $base;
        return trim((string)$base, '-');
    }

    private static function semAcentos(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        return $convertido !== false ? $convertido : $texto;
    }

    public static function criar(string $nome, string $uf, bool $ativo = true): int
    {
        $slug = self::gerarSlug($nome, $uf);
        $existente = self::buscarPorSlug($slug);
        if ($existente) {
            return (int)$existente['id'];
        }
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (nome, uf, slug, ativo, criado_em, atualizado_em) VALUES (?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([$nome, strtoupper($uf), $slug, $ativo ? 1 : 0]);
        return (int)getPDO()->lastInsertId();
    }

    public static function alternarAtivo(int $id): bool
    {
        return getPDO()->prepare("UPDATE " . self::TBL . " SET ativo = NOT ativo, atualizado_em = NOW() WHERE id = ?")
            ->execute([$id]);
    }

    /**
     * Remove uma cidade-alvo e limpa dependências diretas do território.
     *
     * Mantém histórico operacional em pedidos/gastos apenas desvinculando a
     * cidade. Zonas e regras de precificação específicas da cidade são
     * removidas porque deixam de fazer sentido sem a praça.
     *
     * @return array<string, int|string|null>
     */
    public static function removerComDependencias(int $id): array
    {
        $pdo = getPDO();
        $cidade = self::buscarPorId($id);
        if (!$cidade) {
            throw new RuntimeException('Cidade não encontrada.');
        }

        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $stats = [
                'cidade_id' => $id,
                'cidade_nome' => (string)($cidade['nome'] ?? ''),
                'cidade_uf' => (string)($cidade['uf'] ?? ''),
                'cidade_slug' => (string)($cidade['slug'] ?? ''),
                'pricing_zones_excluidas' => 0,
                'service_rules_excluidas' => 0,
                'guinchos_desvinculados' => 0,
                'pedidos_desvinculados' => 0,
                'gastos_desvinculados' => 0,
                'cidade_excluida' => 0,
            ];

            $stmt = $pdo->prepare('DELETE FROM pricing_zones WHERE cidade_id = ?');
            $stmt->execute([$id]);
            $stats['pricing_zones_excluidas'] = $stmt->rowCount();

            $stmt = $pdo->prepare('DELETE FROM service_pricing_rules WHERE cidade_id = ?');
            $stmt->execute([$id]);
            $stats['service_rules_excluidas'] = $stmt->rowCount();

            $stmt = $pdo->prepare('UPDATE guinchos SET cidade_id = NULL WHERE cidade_id = ?');
            $stmt->execute([$id]);
            $stats['guinchos_desvinculados'] = $stmt->rowCount();

            $stmt = $pdo->prepare('UPDATE pedidos SET cidade_id = NULL WHERE cidade_id = ?');
            $stmt->execute([$id]);
            $stats['pedidos_desvinculados'] = $stmt->rowCount();

            $stmt = $pdo->prepare('UPDATE gastos_marketing SET cidade_id = NULL WHERE cidade_id = ?');
            $stmt->execute([$id]);
            $stats['gastos_desvinculados'] = $stmt->rowCount();

            $stmt = $pdo->prepare('DELETE FROM ' . self::TBL . ' WHERE id = ?');
            $stmt->execute([$id]);
            $stats['cidade_excluida'] = $stmt->rowCount();

            if ($stats['cidade_excluida'] < 1) {
                throw new RuntimeException('Cidade não foi excluída.');
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $stats;
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * §PRECO-POR-CIDADE-01: grava o centro geográfico (lat/lng) e o raio de
     * abrangência de uma cidade-alvo — é isso que permite
     * resolverPorCoordenada() decidir automaticamente a qual cidade um
     * pedido pertence, pra segmentar o preço real cobrado do cliente
     * (ver TarifaService e ServicePricingRule). Campos opcionais: uma
     * cidade sem geo configurada simplesmente nunca é resolvida por
     * coordenada (comportamento seguro por padrão).
     */
    public static function atualizarGeo(int $id, ?float $latCentro, ?float $lngCentro, ?int $raioKm): bool
    {
        return getPDO()->prepare(
            "UPDATE " . self::TBL . " SET lat_centro = ?, lng_centro = ?, raio_km = ?, atualizado_em = NOW() WHERE id = ?"
        )->execute([$latCentro, $lngCentro, $raioKm, $id]);
    }

    /**
     * Resolve a cidade-alvo mais próxima de (lat,lng) dentre as cidades
     * ATIVAS que já têm centro geográfico configurado, desde que a
     * distância até o centro não ultrapasse o raio_km cadastrado da
     * cidade. Devolve null quando nenhuma cidade tem geo configurada (ou
     * o ponto está fora do raio de todas) — nesse caso quem chama deve
     * cair no comportamento global de sempre (fallback sem segmentação
     * por cidade), exatamente como ZonePricingService::resolverZonaPorCoordenada
     * faz quando nenhuma zona casa. Aditivo por desenho: enquanto nenhuma
     * cidade tiver lat_centro/lng_centro preenchidos, esta função sempre
     * devolve null e nada muda no cálculo de preço.
     */
    public static function resolverPorCoordenada(float $lat, float $lng): ?array
    {
        $melhor = null;
        $melhorDistancia = null;
        foreach (self::listarAtivas() as $cidade) {
            if ($cidade['lat_centro'] === null || $cidade['lng_centro'] === null) {
                continue;
            }
            $raio = (int)($cidade['raio_km'] ?? 30);
            if ($raio <= 0) {
                continue;
            }
            $distancia = \GeoService::haversine($lat, $lng, (float)$cidade['lat_centro'], (float)$cidade['lng_centro']);
            if ($distancia > $raio) {
                continue;
            }
            if ($melhorDistancia === null || $distancia < $melhorDistancia) {
                $melhor = $cidade;
                $melhorDistancia = $distancia;
            }
        }
        return $melhor;
    }

    /** Contagem de guincheiros vinculados a cada cidade — usado no resumo do admin. */
    public static function contarGuinchosPorCidade(): array
    {
        $stmt = getPDO()->query(
            "SELECT c.id AS cidade_id, COUNT(g.id) AS total
             FROM cidades c
             LEFT JOIN guinchos g ON g.cidade_id = c.id
             GROUP BY c.id"
        );
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['cidade_id']] = (int)$row['total'];
        }
        return $out;
    }
}
