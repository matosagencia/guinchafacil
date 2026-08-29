<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/ServiceExecution.php
// ROADMAP socorro automotivo — Etapa 6 (Proof-of-Service).

class ServiceExecution
{
    private const TBL = 'service_executions';

    public const COMPLETO = 'COMPLETO';
    public const INCOMPLETO = 'INCOMPLETO';

    public static function buscarPorPedido(int $pedidoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Etapa 9 — fila de revisão do admin: execuções cujo checklist ficou
     * INCOMPLETO (faltou diagnóstico e/ou evidência exigida). Traz dados do
     * pedido e do prestador para o admin conseguir dar seguimento manual.
     */
    public static function listarIncompletos(int $limite = 100): array
    {
        $stmt = getPDO()->prepare(
            "SELECT se.*, p.status AS pedido_status, p.criado_em AS pedido_criado_em,
                    u.nome AS prestador_nome, cli.nome AS cliente_nome
             FROM " . self::TBL . " se
             JOIN pedidos p ON p.id = se.pedido_id
             LEFT JOIN guinchos g ON g.id = se.provider_id
             LEFT JOIN usuarios u ON u.id = g.usuario_id
             LEFT JOIN usuarios cli ON cli.id = p.cliente_id
             WHERE se.checklist_status = ?
             ORDER BY se.atualizado_em DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, self::INCOMPLETO, PDO::PARAM_STR);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function contarIncompletos(): int
    {
        $stmt = getPDO()->prepare("SELECT COUNT(*) FROM " . self::TBL . " WHERE checklist_status = ?");
        $stmt->execute([self::INCOMPLETO]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Idempotente via UNIQUE(pedido_id) — reavaliar o mesmo pedido (ex.:
     * evidência chegou depois) atualiza o registro em vez de duplicar.
     */
    public static function registrar(int $pedidoId, int $providerId, string $phaseCode, array $checklist): void
    {
        $status = (!empty($checklist['requires_diagnostic']) && empty($checklist['has_diagnostic']))
            || (!empty($checklist['requires_before_evidence']) && empty($checklist['has_before_evidence']))
            || (!empty($checklist['requires_after_evidence']) && empty($checklist['has_after_evidence']))
            ? self::INCOMPLETO
            : self::COMPLETO;

        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (pedido_id, provider_id, phase_code, requires_diagnostic, requires_before_evidence, requires_after_evidence,
                 has_diagnostic, has_before_evidence, has_after_evidence, checklist_status, avaliado_em, criado_em, atualizado_em)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                provider_id = VALUES(provider_id), phase_code = VALUES(phase_code),
                requires_diagnostic = VALUES(requires_diagnostic), requires_before_evidence = VALUES(requires_before_evidence),
                requires_after_evidence = VALUES(requires_after_evidence), has_diagnostic = VALUES(has_diagnostic),
                has_before_evidence = VALUES(has_before_evidence), has_after_evidence = VALUES(has_after_evidence),
                checklist_status = VALUES(checklist_status), avaliado_em = NOW(), atualizado_em = NOW()"
        );
        $stmt->execute([
            $pedidoId,
            $providerId,
            $phaseCode,
            !empty($checklist['requires_diagnostic']) ? 1 : 0,
            !empty($checklist['requires_before_evidence']) ? 1 : 0,
            !empty($checklist['requires_after_evidence']) ? 1 : 0,
            !empty($checklist['has_diagnostic']) ? 1 : 0,
            !empty($checklist['has_before_evidence']) ? 1 : 0,
            !empty($checklist['has_after_evidence']) ? 1 : 0,
            $status,
        ]);
    }
}
