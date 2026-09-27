<?php
declare(strict_types=1);
// File: guinchafacil/src/Models/OficinaOrcamento.php

require_once __DIR__ . '/../../config.php';

class OficinaOrcamento
{
    private const TBL = 'oficina_orcamentos';

    public static function buscarPendentePorPedido(int $pedidoId): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT o.*, of.nome AS oficina_nome, of.telefone AS oficina_telefone
                 FROM " . self::TBL . " o
                 INNER JOIN oficinas of ON of.id = o.oficina_id
                 WHERE o.pedido_id = ? AND o.status = 'pendente'
                 ORDER BY o.id DESC LIMIT 1"
            );
            $stmt->execute([$pedidoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('[OficinaOrcamento][buscarPendentePorPedido] ' . $e->getMessage());
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
            error_log('[OficinaOrcamento][buscarPorId] ' . $e->getMessage());
            return null;
        }
    }

    public static function aprovar(int $id): bool
    {
        try {
            $stmt = getPDO()->prepare(
                "UPDATE " . self::TBL . "
                 SET status = 'aprovado', respondido_em = NOW()
                 WHERE id = ? AND status = 'pendente'"
            );
            return $stmt->execute([$id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[OficinaOrcamento][aprovar] ' . $e->getMessage());
            return false;
        }
    }

    public static function recusar(int $id): bool
    {
        try {
            $stmt = getPDO()->prepare(
                "UPDATE " . self::TBL . "
                 SET status = 'recusado', respondido_em = NOW()
                 WHERE id = ? AND status = 'pendente'"
            );
            return $stmt->execute([$id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[OficinaOrcamento][recusar] ' . $e->getMessage());
            return false;
        }
    }
}