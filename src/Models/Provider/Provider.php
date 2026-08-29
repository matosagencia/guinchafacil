<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Provider/Provider.php
// ROADMAP socorro automotivo — Etapa 12 (camada aditiva de prestador).
// Ponte sobre `guinchos` — ver install/migration_providers_v1.sql para a
// justificativa completa da estratégia aditiva (decisão do usuário,
// 22/07/2026). `guinchos` continua a fonte de verdade para o fluxo de
// reboque em produção; `providers` é a generalização para os novos tipos
// de prestador (autônomo, oficina, empresa) que ainda não têm cadastro
// próprio nesta sessão.

final class Provider
{
    private const TBL = 'providers';

    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';
    public const TYPE_WORKSHOP = 'WORKSHOP';
    public const TYPE_ROADSIDE_COMPANY = 'ROADSIDE_COMPANY';
    public const TYPE_TOWING_COMPANY = 'TOWING_COMPANY';
    public const TYPE_HYBRID_COMPANY = 'HYBRID_COMPANY';

    public const TYPES = [
        self::TYPE_INDIVIDUAL, self::TYPE_WORKSHOP, self::TYPE_ROADSIDE_COMPANY,
        self::TYPE_TOWING_COMPANY, self::TYPE_HYBRID_COMPANY,
    ];

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Ponte: dado um guincho.id legado, retorna o provider correspondente (sempre deve existir após a migration + backfill). */
    public static function buscarPorGuinchoId(int $guinchoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE legacy_guincho_id = ? LIMIT 1");
        $stmt->execute([$guinchoId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarTodos(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY legal_name ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Cria um provider NOVO, sem vínculo com guincho legado (autônomo, oficina
     * ou empresa cadastrados diretamente no modelo generalizado). Nasce
     * PENDING — aprovação é passo separado, mesmo padrão de ProviderCapability.
     */
    public static function criar(array $dados): int
    {
        if (!in_array($dados['provider_type'], self::TYPES, true)) {
            throw new \InvalidArgumentException("provider_type inválido: {$dados['provider_type']}");
        }

        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (provider_type, legal_name, trade_name, document_type, document_number,
                 approval_status, payment_recipient_type, pix_key, active, legacy_guincho_id,
                 created_at, updated_at)
             VALUES (?,?,?,?,?, 'PENDING', ?, ?, 1, NULL, NOW(), NOW())"
        );
        $stmt->execute([
            $dados['provider_type'],
            $dados['legal_name'],
            $dados['trade_name'] ?? null,
            $dados['document_type'] ?? null,
            $dados['document_number'] ?? null,
            $dados['payment_recipient_type'] ?? self::TYPE_INDIVIDUAL,
            $dados['pix_key'] ?? null,
        ]);
        return (int)getPDO()->lastInsertId();
    }
}
