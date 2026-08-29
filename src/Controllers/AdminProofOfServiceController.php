<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminProofOfServiceController.php
// ROADMAP socorro automotivo — Etapa 9 (telas admin especializadas).
// Fila de revisão dos checklists de Proof-of-Service (Etapa 6) que ficaram
// INCOMPLETOS. Controller próprio (não sobrecarrega AdminController).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/ServiceExecution.php';

class AdminProofOfServiceController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function checklistsIncompletos(): void
    {
        AuthService::requireAuth('admin');
        $execucoes = ServiceExecution::listarIncompletos();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/proof_of_service_incompletos.php';
    }
}
