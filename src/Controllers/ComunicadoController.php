<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/ComunicadoService.php';

class ComunicadoController extends BaseController
{
    public function carousel(): void
    {
        $profile = (string)($_GET['profile'] ?? 'cliente');
        if (!in_array($profile, ['cliente', 'guincho'], true)) {
            $profile = 'cliente';
        }
        AuthService::requireAuth($profile, false);
        $placement = (string)($_GET['placement'] ?? '');
        $items = ComunicadoService::resolveActiveForProfile($profile, $placement, 5);
        header('Content-Type: text/html; charset=UTF-8');
        $comunicados = $items;
        require __DIR__ . '/../Views/components/communication_carousel.php';
    }
}
