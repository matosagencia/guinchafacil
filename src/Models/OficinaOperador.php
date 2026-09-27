<?php
declare(strict_types=1);
// File: guinchafacil/src/Models/OficinaOperador.php
// Tabela: oficinas (operadora/parceira)
// NAO CONFUNDIR com Oficina.php (tabela oficinas_favoritas do cliente)

require_once __DIR__ . '/../../config.php';

class OficinaOperador
{
    private const TBL = 'oficinas';

    public static function buscarPorUsuarioId(int $usuarioId): ?array
    {
        try {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('[OficinaOperador][buscarPorUsuarioId] ' . $e->getMessage());
            return null;
        }
    }

    public static function buscarPorId(int $id): ?array
    {
        try {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('[OficinaOperador][buscarPorId] ' . $e->getMessage());
            return null;
        }
    }

    public static function listarAtivas(): array
    {
        try {
            $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE ativo = 1 ORDER BY nome ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[OficinaOperador][listarAtivas] ' . $e->getMessage());
            return [];
        }
    }

    public static function atualizarDisponibilidade(int $id, bool $disponivel): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET disponivel = ? WHERE id = ?");
            return (bool)$stmt->execute([$disponivel ? 1 : 0, $id]);
        } catch (PDOException $e) {
            error_log('[OficinaOperador][atualizarDisponibilidade] ' . $e->getMessage());
            return false;
        }
    }

    public static function atualizarLocalizacao(int $id, float $lat, float $lng): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET latitude = ?, longitude = ? WHERE id = ?");
            return (bool)$stmt->execute([$lat, $lng, $id]);
        } catch (PDOException $e) {
            error_log('[OficinaOperador][atualizarLocalizacao] ' . $e->getMessage());
            return false;
        }
    }
}