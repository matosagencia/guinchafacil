<?php

declare(strict_types=1);

/**
 * src/Models/ServicoCatalogo.php
 * Catálogo de serviços (atalhos "Reboque agora / Bateria / Pneu / Pane seca /
 * Agendar" no painel do cliente) — antes fixo no código, agora administrável
 * pelo admin (tabela `catalogo_servicos`). Fecha o gap "catálogo de serviços
 * administrável" do gate de saída do Nível 2.
 */
class ServicoCatalogo
{
    private const TBL = 'catalogo_servicos';

    public static function listarAtivos(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT * FROM " . self::TBL . " WHERE ativo = 1 ORDER BY ordem ASC, id ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ServicoCatalogo::listarAtivos: ' . $e->getMessage());
            return [];
        }
    }

    public static function listarTodos(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT * FROM " . self::TBL . " ORDER BY ordem ASC, id ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ServicoCatalogo::listarTodos: ' . $e->getMessage());
            return [];
        }
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criar(array $dados): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (chave, nome, descricao, tipo_problema, icone, cor, ordem, ativo, criado_em, atualizado_em)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            $dados['chave'],
            $dados['nome'],
            $dados['descricao'] ?? null,
            $dados['tipo_problema'] ?? 'outro',
            $dados['icone'] ?? 'fa-truck-pickup',
            $dados['cor'] ?? 'tow',
            (int)($dados['ordem'] ?? 100),
            !empty($dados['ativo']) ? 1 : 0,
        ]);
        return (int)getPDO()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . "
             SET chave=?, nome=?, descricao=?, tipo_problema=?, icone=?, cor=?, ordem=?, ativo=?, atualizado_em=NOW()
             WHERE id=?"
        );
        return $stmt->execute([
            $dados['chave'],
            $dados['nome'],
            $dados['descricao'] ?? null,
            $dados['tipo_problema'] ?? 'outro',
            $dados['icone'] ?? 'fa-truck-pickup',
            $dados['cor'] ?? 'tow',
            (int)($dados['ordem'] ?? 100),
            !empty($dados['ativo']) ? 1 : 0,
            $id,
        ]);
    }

    public static function alternarAtivo(int $id): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET ativo = 1 - ativo, atualizado_em = NOW() WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    public static function remover(int $id): bool
    {
        $stmt = getPDO()->prepare("DELETE FROM " . self::TBL . " WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
