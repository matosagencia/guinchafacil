<?php
declare(strict_types=1);

/**
 * §9 — Bloqueio financeiro operacional.
 *
 * Regra: guincho bloqueado se existe fatura com parceiro_tipo='guincho',
 * parceiro_id = :id e status IN ('vencida','bloqueada') em faturas_parceiro.
 *
 * Parceiro bloqueado:
 *   - não aparece no anel de candidatos (§7)
 *   - não recebe oferta
 *   - NÃO recebe notificação de bloqueio até quitar (decisão de produto)
 *   - POST direto em /guincho/aceitar recebe mensagem neutra, sem revelar motivo
 */
final class BloqueioFinanceiroService
{
    /** Cache por request para consultas de 1 em 1. */
    private static array $cache = [];

    public static function guinchoEstaBloqueado(int $guinchoId): bool
    {
        if ($guinchoId <= 0) {
            return false;
        }
        if (array_key_exists($guinchoId, self::$cache)) {
            return self::$cache[$guinchoId];
        }

        $stmt = getPDO()->prepare(
            "SELECT 1
               FROM faturas_parceiro
              WHERE parceiro_tipo = 'guincho'
                AND parceiro_id   = :id
                AND status IN ('vencida','bloqueada')
              LIMIT 1"
        );
        $stmt->execute([':id' => $guinchoId]);

        return self::$cache[$guinchoId] = (bool)$stmt->fetchColumn();
    }

    /**
     * Versão batch — evita N+1 em candidatosPorAnel (Fase D.2).
     *
     * @return int[]
     */
    public static function idsBloqueados(): array
    {
        $stmt = getPDO()->query(
            "SELECT DISTINCT parceiro_id
               FROM faturas_parceiro
              WHERE parceiro_tipo = 'guincho'
                AND status IN ('vencida','bloqueada')"
        );

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Detalhe para o painel admin / debug — devolve a fatura mais antiga
     * em aberto vencida ou bloqueada, ou null se não há bloqueio.
     */
    public static function motivo(int $guinchoId): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT id, status, saldo, vencimento_em
               FROM faturas_parceiro
              WHERE parceiro_tipo = 'guincho'
                AND parceiro_id   = :id
                AND status IN ('vencida','bloqueada')
              ORDER BY vencimento_em ASC
              LIMIT 1"
        );
        $stmt->execute([':id' => $guinchoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Limpa o cache — usado em testes. */
    public static function limparCache(): void
    {
        self::$cache = [];
    }
}