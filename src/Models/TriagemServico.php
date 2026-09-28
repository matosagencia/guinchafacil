<?php
declare(strict_types=1);

final class TriagemServico
{
    public static function listarAtivos(): array
    {
        try {
            $stmt = getPDO()->query("
                SELECT id, slug, nome, descricao, icone, oficina_tipo, service_type_code, ordem, ativo
                  FROM precotacao_triagem_servicos
                 WHERE ativo = 1
                 ORDER BY ordem ASC, id ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[TriagemServico::listarAtivos] ' . $e->getMessage());
            return [];
        }
    }

    public static function listarTodos(): array
    {
        try {
            $stmt = getPDO()->query("
                SELECT id, slug, nome, descricao, icone, oficina_tipo, service_type_code, ordem, ativo
                  FROM precotacao_triagem_servicos
                 ORDER BY ordem ASC, id ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[TriagemServico::listarTodos] ' . $e->getMessage());
            return [];
        }
    }

    public static function buscarPorSlug(string $slug): ?array
    {
        try {
            $stmt = getPDO()->prepare("
                SELECT id, slug, nome, descricao, icone, oficina_tipo, service_type_code, ordem, ativo
                  FROM precotacao_triagem_servicos
                 WHERE slug = ? LIMIT 1
            ");
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[TriagemServico::buscarPorSlug] ' . $e->getMessage());
            return null;
        }
    }

    public static function criar(array $dados): int
    {
        $stmt = getPDO()->prepare("
            INSERT INTO precotacao_triagem_servicos
                (slug, nome, descricao, icone, oficina_tipo, service_type_code, ordem, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (string)$dados['slug'],
            (string)$dados['nome'],
            (string)($dados['descricao'] ?? ''),
            (string)($dados['icone'] ?? ''),
            (string)($dados['oficina_tipo'] ?? ''),
            ((string)($dados['service_type_code'] ?? '')) ?: null,
            (int)($dados['ordem'] ?? 100),
            (int)($dados['ativo'] ?? 0),
        ]);
        return (int)getPDO()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = getPDO()->prepare("
            UPDATE precotacao_triagem_servicos
               SET slug = ?, nome = ?, descricao = ?, icone = ?,
                   oficina_tipo = ?, service_type_code = ?, ordem = ?
             WHERE id = ?
        ");
        return $stmt->execute([
            (string)$dados['slug'],
            (string)$dados['nome'],
            (string)($dados['descricao'] ?? ''),
            (string)($dados['icone'] ?? ''),
            (string)($dados['oficina_tipo'] ?? ''),
            ((string)($dados['service_type_code'] ?? '')) ?: null,
            (int)($dados['ordem'] ?? 100),
            $id,
        ]);
    }

    public static function alternarAtivo(int $id): bool
    {
        $stmt = getPDO()->prepare("
            UPDATE precotacao_triagem_servicos
               SET ativo = 1 - ativo
             WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}
