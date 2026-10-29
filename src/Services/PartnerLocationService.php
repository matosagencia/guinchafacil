<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

/**
 * Autoridade de leitura da localização operacional de parceiros.
 *
 * A localização do cliente é própria do pedido. A localização operacional de
 * guinchos/oficinas deve vir do cadastro do parceiro; o legado só é fallback
 * quando existe vínculo explícito em oficina_provider_links.
 */
final class PartnerLocationService
{
    /**
     * Vincula explicitamente o cadastro legado ao parceiro operacional.
     * A operação é deliberadamente explícita: nome parecido não é prova de
     * identidade e não deve mover coordenadas entre oficinas diferentes.
     */
    public static function vincularOficinaLegada(int $providerId, int $oficinaId): array
    {
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            $provider = $pdo->prepare("SELECT id, provider_type FROM providers WHERE id = ? FOR UPDATE");
            $provider->execute([$providerId]);
            $providerRow = $provider->fetch(PDO::FETCH_ASSOC);
            if (!$providerRow || $providerRow['provider_type'] !== 'WORKSHOP') {
                throw new InvalidArgumentException('O provider informado não é uma oficina.');
            }

            $oficina = $pdo->prepare('SELECT id, nome, endereco, latitude, longitude FROM oficinas WHERE id = ? FOR UPDATE');
            $oficina->execute([$oficinaId]);
            $oficinaRow = $oficina->fetch(PDO::FETCH_ASSOC);
            if (!$oficinaRow) {
                throw new InvalidArgumentException('Cadastro legado da oficina não encontrado.');
            }
            if ($oficinaRow['latitude'] === null || $oficinaRow['longitude'] === null) {
                throw new InvalidArgumentException('A oficina legada não possui coordenadas válidas.');
            }

            $conflict = $pdo->prepare('SELECT oficina_id FROM oficina_provider_links WHERE provider_id = ? FOR UPDATE');
            $conflict->execute([$providerId]);
            $linkedOffice = $conflict->fetchColumn();
            if ($linkedOffice !== false && (int)$linkedOffice !== $oficinaId) {
                throw new RuntimeException('O provider já está vinculado a outra oficina.');
            }

            $pdo->prepare(
                'INSERT INTO oficina_provider_links (oficina_id, provider_id)
                 VALUES (?, ?) ON DUPLICATE KEY UPDATE provider_id = VALUES(provider_id)'
            )->execute([$oficinaId, $providerId]);
            $pdo->prepare(
                'UPDATE provider_workshop_settings
                    SET address = ?, latitude = ?, longitude = ?, updated_at = NOW()
                  WHERE provider_id = ?'
            )->execute([$oficinaRow['endereco'], $oficinaRow['latitude'], $oficinaRow['longitude'], $providerId]);

            $pdo->commit();
            return [
                'provider_id' => $providerId,
                'oficina_id' => $oficinaId,
                'nome' => $oficinaRow['nome'],
                'latitude' => (float)$oficinaRow['latitude'],
                'longitude' => (float)$oficinaRow['longitude'],
                'location_source' => 'legacy_link',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function listarWorkshopsOperacionais(): array
    {
        $pdo = getPDO();
        $joinLegado = '';
        $selectLegado = '';

        if (self::tableExists($pdo, 'oficina_provider_links')) {
            $joinLegado = ' LEFT JOIN oficina_provider_links opl ON opl.provider_id = p.id
                            LEFT JOIN oficinas legacy_o ON legacy_o.id = opl.oficina_id';
            $selectLegado = ', legacy_o.latitude AS legacy_latitude, legacy_o.longitude AS legacy_longitude';
        }

        $sql = "SELECT p.id AS provider_id,
                       COALESCE(NULLIF(TRIM(p.trade_name), ''), p.legal_name) AS nome,
                       ws.latitude, ws.longitude,
                       COALESCE(ws.taxa_resgate_direto, 0) AS taxa_resgate_direto,
                       COALESCE(NULLIF(ws.raio_resgate_direto_km, 0), 30) AS raio_resgate_direto_km
                       {$selectLegado}
                  FROM providers p
                  JOIN provider_workshop_settings ws ON ws.provider_id = p.id
                       {$joinLegado}
                 WHERE p.provider_type = 'WORKSHOP'
                   AND p.active = 1 AND p.approval_status = 'APPROVED'
                   AND ws.status_parceria = 'ATIVO' AND ws.faz_resgate_direto = 1";

        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[PartnerLocationService] Falha ao listar oficinas: ' . $e->getMessage());
            return [];
        }

        foreach ($rows as &$row) {
            if (($row['latitude'] === null || $row['longitude'] === null)
                && isset($row['legacy_latitude'], $row['legacy_longitude'])
                && $row['legacy_latitude'] !== null && $row['legacy_longitude'] !== null) {
                $row['latitude'] = $row['legacy_latitude'];
                $row['longitude'] = $row['legacy_longitude'];
                $row['location_source'] = 'legacy_link';
            } elseif ($row['latitude'] !== null && $row['longitude'] !== null) {
                $row['location_source'] = 'provider_settings';
            } else {
                $row['location_source'] = 'missing';
            }
        }
        unset($row);

        return $rows;
    }

    /** Lista completa para diagnóstico, inclusive providers inelegíveis. */
    public static function listarWorkshopsParaDiagnostico(): array
    {
        $pdo = getPDO();
        $sql = "SELECT p.id,p.trade_name,p.legal_name,p.approval_status,p.active,
                       ws.status_parceria,ws.recebe_veiculo_patio,ws.faz_resgate_direto,
                       ws.latitude AS p_lat,ws.longitude AS p_lng,
                       l.oficina_id,o.latitude AS o_lat,o.longitude AS o_lng
                  FROM providers p
                  LEFT JOIN provider_workshop_settings ws ON ws.provider_id=p.id
                  LEFT JOIN oficina_provider_links l ON l.provider_id=p.id
                  LEFT JOIN oficinas o ON o.id=l.oficina_id
                 WHERE p.provider_type='WORKSHOP' ORDER BY p.id";
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[PartnerLocationService] Falha no diagnostico de oficinas: ' . $e->getMessage());
            return [];
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
