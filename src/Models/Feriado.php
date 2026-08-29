<?php

declare(strict_types=1);

/**
 * src/Models/Feriado.php
 * §A6 — feriados administráveis pelo admin (tabela `feriados`), usados por
 * TarifaService::isFeriado() pra aplicar o adicional de feriado na tarifa.
 */
class Feriado
{
    private const TBL = 'feriados';

    public static function listarTodos(): array
    {
        try {
            $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY data ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Feriado::listarTodos: ' . $e->getMessage());
            return [];
        }
    }

    public static function listarAtivos(): array
    {
        try {
            $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE ativo = 1 ORDER BY data ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Feriado::listarAtivos: ' . $e->getMessage());
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
            "INSERT INTO " . self::TBL . " (data, nome, recorrente_anual, ativo, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $dados['data'],
            $dados['nome'],
            !empty($dados['recorrente_anual']) ? 1 : 0,
            !empty($dados['ativo']) ? 1 : 0,
        ]);
        return (int)getPDO()->lastInsertId();
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
