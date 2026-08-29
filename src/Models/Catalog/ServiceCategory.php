<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ServiceCategory.php
// ROADMAP socorro automotivo — Fundamento 1 (catálogo de serviços).

class ServiceCategory
{
    private const TBL = 'service_categories';

    public static function listarAtivas(): array
    {
        $stmt = getPDO()->query(
            "SELECT * FROM " . self::TBL . " WHERE active = 1 ORDER BY sort_order ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarTodas(): array
    {
        $stmt = getPDO()->query(
            "SELECT * FROM " . self::TBL . " ORDER BY sort_order ASC, id ASC"
        );
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
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criar(array $dados): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (code, name, description, icon, active, sort_order, created_at, updated_at)
             VALUES (?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            strtoupper(trim((string)$dados['code'])),
            (string)$dados['name'],
            $dados['description'] ?? null,
            $dados['icon'] ?? null,
            !empty($dados['active']) ? 1 : 0,
            (int)($dados['sort_order'] ?? 0),
        ]);
        return (int)getPDO()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . "
             SET name = ?, description = ?, icon = ?, active = ?, sort_order = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([
            (string)$dados['name'],
            $dados['description'] ?? null,
            $dados['icon'] ?? null,
            !empty($dados['active']) ? 1 : 0,
            (int)($dados['sort_order'] ?? 0),
            $id,
        ]);
    }
}
