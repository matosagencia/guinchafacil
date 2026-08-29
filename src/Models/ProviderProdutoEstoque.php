<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/ProviderProdutoEstoque.php
// ROADMAP socorro automotivo — Etapa 8. Estoque por prestador (= guinchos.id).

class ProviderProdutoEstoque
{
    private const TBL = 'provider_produtos_estoque';

    public static function listarPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT e.*, p.sku, p.nome, p.categoria, p.especificacao, p.preco_referencia, p.unidade
             FROM " . self::TBL . " e
             JOIN produtos p ON p.id = e.produto_id
             WHERE e.provider_id = ?
             ORDER BY p.categoria ASC, p.nome ASC"
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscar(int $providerId, int $produtoId): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . " WHERE provider_id = ? AND produto_id = ? LIMIT 1"
        );
        $stmt->execute([$providerId, $produtoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Preço efetivo de venda: override do estoque ou preço de referência do produto. */
    public static function precoEfetivo(array $linhaEstoque): float
    {
        if ($linhaEstoque['preco_venda'] !== null && $linhaEstoque['preco_venda'] !== '') {
            return (float)$linhaEstoque['preco_venda'];
        }
        return (float)($linhaEstoque['preco_referencia'] ?? 0);
    }

    /** Upsert idempotente via UNIQUE(provider_id, produto_id). Ajusta preço/quantidade absolutos. */
    public static function definir(int $providerId, int $produtoId, int $quantidade, ?float $precoVenda, bool $active = true): int
    {
        $pdo = getPDO();
        $existente = self::buscar($providerId, $produtoId);
        if ($existente) {
            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . " SET quantidade = ?, preco_venda = ?, active = ?, atualizado_em = NOW() WHERE id = ?"
            );
            $stmt->execute([max(0, $quantidade), $precoVenda, $active ? 1 : 0, (int)$existente['id']]);
            return (int)$existente['id'];
        }
        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . " (provider_id, produto_id, quantidade, preco_venda, active, criado_em, atualizado_em)
             VALUES (?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([$providerId, $produtoId, max(0, $quantidade), $precoVenda, $active ? 1 : 0]);
        return (int)$pdo->lastInsertId();
    }
}
