<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';

/** Controller especializado do histórico de auditoria do ambiente. */
final class AdminEnvAuditController extends BaseController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');
        try {
            $registros = getPDO()->query(
                "SELECT ea.*, u.nome AS admin_nome
                 FROM env_auditoria ea
                 LEFT JOIN usuarios u ON u.id = ea.admin_id
                 ORDER BY ea.criado_em DESC LIMIT 200"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $registros = [];
        }
        require __DIR__ . '/../Views/admin/env_auditoria.php';
    }
}
