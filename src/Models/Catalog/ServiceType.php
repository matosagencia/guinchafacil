<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ServiceType.php
// ROADMAP socorro automotivo — Fundamento 1 (catálogo de serviços).
//
// Regra do roadmap (§2.3): nenhum controller deve decidir comportamento
// comparando texto livre (`if ($pedido['problema'] === 'bateria')`) — a
// decisão vem sempre destes campos estruturados do tipo de serviço.

class ServiceType
{
    private const TBL = 'service_types';

    public static function listarAtivos(): array
    {
        $stmt = getPDO()->query(
            "SELECT st.*, sc.code AS category_code, sc.name AS category_name
             FROM " . self::TBL . " st
             JOIN service_categories sc ON sc.id = st.category_id
             WHERE st.active = 1
             ORDER BY sc.sort_order ASC, st.name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarTodos(): array
    {
        $stmt = getPDO()->query(
            "SELECT st.*, sc.code AS category_code, sc.name AS category_name
             FROM " . self::TBL . " st
             JOIN service_categories sc ON sc.id = st.category_id
             ORDER BY sc.sort_order ASC, st.name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorCategoria(int $categoryId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . " WHERE category_id = ? AND active = 1 ORDER BY name ASC"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorCodigo(string $code): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE code = ? LIMIT 1");
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── Leitores de regra estrutural (§2.3 do roadmap) ─────────────────────

    public static function requiresDestination(array $serviceType): bool
    {
        return (bool)($serviceType['requires_destination'] ?? false);
    }

    public static function allowsConversionToTowing(array $serviceType): bool
    {
        return (bool)($serviceType['allows_conversion_to_towing'] ?? false);
    }

    public static function requiresDiagnostic(array $serviceType): bool
    {
        return (bool)($serviceType['requires_diagnostic'] ?? false);
    }

    public static function requiresParts(array $serviceType): bool
    {
        return (bool)($serviceType['requires_parts'] ?? false);
    }

    /** Etapa 6 (Proof-of-Service) — exige foto de "antes" (chegada/diagnóstico)? Default true na criação (ver criar()). */
    public static function requiresBeforeEvidence(array $serviceType): bool
    {
        return (bool)($serviceType['requires_before_evidence'] ?? true);
    }

    /** Etapa 6 (Proof-of-Service) — exige foto de "depois" (conclusão)? Default true na criação (ver criar()). */
    public static function requiresAfterEvidence(array $serviceType): bool
    {
        return (bool)($serviceType['requires_after_evidence'] ?? true);
    }

    public static function isTowing(array $serviceType): bool
    {
        return (string)($serviceType['attendance_mode'] ?? '') === 'TOWING';
    }

    public static function criar(array $dados): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (category_id, code, name, description, attendance_mode, requires_destination,
                 allows_conversion_to_towing, requires_diagnostic, requires_parts,
                 requires_before_evidence, requires_after_evidence, estimated_duration_minutes, active,
                 created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            (int)$dados['category_id'],
            strtoupper(trim((string)$dados['code'])),
            (string)$dados['name'],
            $dados['description'] ?? null,
            (string)($dados['attendance_mode'] ?? 'ON_SITE'),
            !empty($dados['requires_destination']) ? 1 : 0,
            !empty($dados['allows_conversion_to_towing']) ? 1 : 0,
            !empty($dados['requires_diagnostic']) ? 1 : 0,
            !empty($dados['requires_parts']) ? 1 : 0,
            array_key_exists('requires_before_evidence', $dados) ? (!empty($dados['requires_before_evidence']) ? 1 : 0) : 1,
            array_key_exists('requires_after_evidence', $dados) ? (!empty($dados['requires_after_evidence']) ? 1 : 0) : 1,
            (int)($dados['estimated_duration_minutes'] ?? 30),
            !empty($dados['active']) ? 1 : 0,
        ]);
        return (int)getPDO()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . "
             SET category_id = ?, name = ?, description = ?, attendance_mode = ?, requires_destination = ?,
                 allows_conversion_to_towing = ?, requires_diagnostic = ?, requires_parts = ?,
                 requires_before_evidence = ?, requires_after_evidence = ?, estimated_duration_minutes = ?,
                 active = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([
            (int)$dados['category_id'],
            (string)$dados['name'],
            $dados['description'] ?? null,
            (string)($dados['attendance_mode'] ?? 'ON_SITE'),
            !empty($dados['requires_destination']) ? 1 : 0,
            !empty($dados['allows_conversion_to_towing']) ? 1 : 0,
            !empty($dados['requires_diagnostic']) ? 1 : 0,
            !empty($dados['requires_parts']) ? 1 : 0,
            !empty($dados['requires_before_evidence']) ? 1 : 0,
            !empty($dados['requires_after_evidence']) ? 1 : 0,
            (int)($dados['estimated_duration_minutes'] ?? 30),
            !empty($dados['active']) ? 1 : 0,
            $id,
        ]);
    }
}
