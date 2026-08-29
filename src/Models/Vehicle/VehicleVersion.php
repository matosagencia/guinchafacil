<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Vehicle/VehicleVersion.php
// ROADMAP socorro automotivo — Etapa 14 (catálogo veicular estruturado).

class VehicleVersion
{
    private const TBL = 'vehicle_versions';

    public static function listarPorModelo(int $modelId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT v.*, voc.code AS categoria_code, voc.name AS categoria_nome
             FROM " . self::TBL . " v
             JOIN vehicle_operational_categories voc ON voc.id = v.operational_category_id
             WHERE v.model_id = ? AND v.active = 1
             ORDER BY v.name ASC"
        );
        $stmt->execute([$modelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT v.*, vm.name AS modelo_nome, vm.brand_id, vb.name AS marca_nome,
                    voc.code AS categoria_code, voc.name AS categoria_nome, voc.max_weight_kg, voc.requires_platform_default
             FROM " . self::TBL . " v
             JOIN vehicle_models vm ON vm.id = v.model_id
             JOIN vehicle_brands vb ON vb.id = vm.brand_id
             JOIN vehicle_operational_categories voc ON voc.id = v.operational_category_id
             WHERE v.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criar(int $modelId, array $dados): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (model_id, name, start_year, end_year, engine, fuel_type, transmission_type, traction_type,
                 body_type, start_stop, electric_type, operational_category_id, curb_weight_kg, gross_weight_kg,
                 length_mm, height_mm, active, criado_em)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())"
        );
        $stmt->execute([
            $modelId,
            trim((string)$dados['name']),
            $dados['start_year'] !== '' ? (int)$dados['start_year'] : null,
            $dados['end_year'] !== '' ? (int)$dados['end_year'] : null,
            $dados['engine'] ?: null,
            $dados['fuel_type'] ?: null,
            $dados['transmission_type'] ?: null,
            $dados['traction_type'] ?: null,
            $dados['body_type'] ?: null,
            !empty($dados['start_stop']) ? 1 : 0,
            $dados['electric_type'] ?: null,
            (int)$dados['operational_category_id'],
            $dados['curb_weight_kg'] !== '' ? (int)$dados['curb_weight_kg'] : null,
            $dados['gross_weight_kg'] !== '' ? (int)$dados['gross_weight_kg'] : null,
            $dados['length_mm'] !== '' ? (int)$dados['length_mm'] : null,
            $dados['height_mm'] !== '' ? (int)$dados['height_mm'] : null,
        ]);
        return (int)getPDO()->lastInsertId();
    }

    /** §CATALOGO-VISUAL-01: edição de versão existente (mesmos campos de criar()). */
    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . "
             SET name = ?, start_year = ?, end_year = ?, engine = ?, fuel_type = ?, transmission_type = ?,
                 traction_type = ?, body_type = ?, start_stop = ?, electric_type = ?, operational_category_id = ?,
                 curb_weight_kg = ?, gross_weight_kg = ?, length_mm = ?, height_mm = ?, active = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            trim((string)$dados['name']),
            $dados['start_year'] !== '' ? (int)$dados['start_year'] : null,
            $dados['end_year'] !== '' ? (int)$dados['end_year'] : null,
            $dados['engine'] ?: null,
            $dados['fuel_type'] ?: null,
            $dados['transmission_type'] ?: null,
            $dados['traction_type'] ?: null,
            $dados['body_type'] ?: null,
            !empty($dados['start_stop']) ? 1 : 0,
            $dados['electric_type'] ?: null,
            (int)$dados['operational_category_id'],
            $dados['curb_weight_kg'] !== '' ? (int)$dados['curb_weight_kg'] : null,
            $dados['gross_weight_kg'] !== '' ? (int)$dados['gross_weight_kg'] : null,
            $dados['length_mm'] !== '' ? (int)$dados['length_mm'] : null,
            $dados['height_mm'] !== '' ? (int)$dados['height_mm'] : null,
            !empty($dados['active']) ? 1 : 0,
            $id,
        ]);
    }
}
