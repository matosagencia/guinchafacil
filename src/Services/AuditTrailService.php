<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

/**
 * §AUDIT-01 — Registro de eventos de negócio críticos.
 *
 * Wrapper sobre Logger com nível INFO e system='auditoria'.
 * Centraliza eventos rastreáveis: pagamentos, repasses, avaliações, webhooks.
 */
class AuditTrailService
{
    /**
     * Registra um evento de auditoria.
     *
     * @param string $evento   Ex.: 'pagamento_aprovado', 'pix_transferido', 'avaliacao_criada'
     * @param string $cls      Classe chamadora
     * @param string $func     Método chamador
     * @param array  $ctx      Contexto adicional (IDs, valores, etc.)
     */
    public static function evento(
        string $evento,
        string $cls,
        string $func,
        array $ctx = []
    ): void {
        Logger::event([
            'level' => Logger::LEVEL_INFO,
            'class' => $cls,
            'function' => $func,
            'system' => 'AUDITORIA',
            'phase' => 'business_event',
            'code' => (string)($ctx['event_code'] ?? $evento),
            'message' => "AUDIT[{$evento}]",
            'pedido_id' => $ctx['pedido_id'] ?? null,
            'usuario_id' => $ctx['usuario_id'] ?? ($ctx['actor_id'] ?? null),
            'run_id' => $ctx['run_id'] ?? null,
            'context' => array_merge(['event_name' => $evento], $ctx),
        ]);
    }
}
