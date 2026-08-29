<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/ServiceTypeProduto.php
// Catálogo de peças por categoria de serviço — pré-seleção do orçamento.

class ServiceTypeProduto
{
    private const TBL = 'service_type_produtos';

    /**
     * Produtos sugeridos (pré-seleção) para um tipo de serviço, com o preço
     * médio de referência. Usado ao montar o orçamento complementar (Etapa 5).
     */
    public static function listarPorServico(int $serviceTypeId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT stp.sugerido, p.id, p.sku, p.nome, p.categoria, p.especificacao,
                    p.preco_referencia, p.unidade
             FROM " . self::TBL . " stp
             JOIN produtos p ON p.id = stp.produto_id
             WHERE stp.service_type_id = ? AND p.active = 1
             ORDER BY p.categoria ASC, p.preco_referencia ASC"
        );
        $stmt->execute([$serviceTypeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Idempotente via UNIQUE(service_type_id, produto_id). */
    public static function associar(int $serviceTypeId, int $produtoId, bool $sugerido = true): void
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (service_type_id, produto_id, sugerido, criado_em)
             VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE sugerido = VALUES(sugerido)"
        );
        $stmt->execute([$serviceTypeId, $produtoId, $sugerido ? 1 : 0]);
    }

    public static function desassociar(int $serviceTypeId, int $produtoId): void
    {
        $stmt = getPDO()->prepare(
            "DELETE FROM " . self::TBL . " WHERE service_type_id = ? AND produto_id = ?"
        );
        $stmt->execute([$serviceTypeId, $produtoId]);
    }
}
