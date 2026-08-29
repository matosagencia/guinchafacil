<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Produto.php
// ROADMAP socorro automotivo — Etapa 8 (produtos e estoque). Catálogo global.

class Produto
{
    private const TBL = 'produtos';

    public static function listarTodos(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY categoria ASC, nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarAtivosPorCategoria(string $categoria): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE categoria = ? AND active = 1 ORDER BY nome ASC");
        $stmt->execute([$categoria]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criar(array $d): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (sku, nome, categoria, descricao, especificacao, preco_referencia, unidade, active, criado_em, atualizado_em)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            strtoupper(trim((string)$d['sku'])),
            trim((string)$d['nome']),
            trim((string)($d['categoria'] ?? 'bateria')),
            trim((string)($d['descricao'] ?? '')) ?: null,
            trim((string)($d['especificacao'] ?? '')) ?: null,
            ($d['preco_referencia'] ?? '') !== '' ? (float)$d['preco_referencia'] : null,
            trim((string)($d['unidade'] ?? 'un')) ?: 'un',
            !empty($d['active']) ? 1 : 0,
        ]);
        return (int)getPDO()->lastInsertId();
    }

    public static function atualizar(int $id, array $d): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET nome = ?, categoria = ?, descricao = ?, especificacao = ?, preco_referencia = ?, unidade = ?, active = ?, atualizado_em = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([
            trim((string)$d['nome']),
            trim((string)($d['categoria'] ?? 'bateria')),
            trim((string)($d['descricao'] ?? '')) ?: null,
            trim((string)($d['especificacao'] ?? '')) ?: null,
            ($d['preco_referencia'] ?? '') !== '' ? (float)$d['preco_referencia'] : null,
            trim((string)($d['unidade'] ?? 'un')) ?: 'un',
            !empty($d['active']) ? 1 : 0,
            $id,
        ]);
    }
}
