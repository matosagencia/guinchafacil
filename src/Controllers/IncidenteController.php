<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/EspecialistaAtendimentoService.php';
require_once __DIR__ . '/../Services/TowEscalationService.php';

final class IncidenteController extends BaseController
{
    public function aprovarOrcamento(int $id = 0): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        try { EspecialistaAtendimentoService::decidirOrcamento($id, (int)($_SESSION['user']['id'] ?? 0), true); $this->redirect('/cliente/dashboard?msg=orcamento_aprovado'); }
        catch (Throwable $e) { $this->redirect('/cliente/dashboard?erro='.rawurlencode($e->getMessage())); }
    }

    public function recusarOrcamento(int $id = 0): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        try { EspecialistaAtendimentoService::decidirOrcamento($id, (int)($_SESSION['user']['id'] ?? 0), false); $this->redirect('/cliente/dashboard?msg=orcamento_recusado'); }
        catch (Throwable $e) { $this->redirect('/cliente/dashboard?erro='.rawurlencode($e->getMessage())); }
    }

    public function solicitarReboque(int $id = 0): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        try { TowEscalationService::solicitar($id, (int)($_SESSION['user']['id'] ?? 0)); $this->redirect('/cliente/dashboard?msg=reboque_solicitado'); }
        catch (Throwable $e) { $this->redirect('/cliente/dashboard?erro='.rawurlencode($e->getMessage())); }
    }
}
