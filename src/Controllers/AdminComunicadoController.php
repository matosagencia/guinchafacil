<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Comunicado.php';
require_once __DIR__ . '/../Services/ComunicadoService.php';
require_once __DIR__ . '/../Services/MediaUploadService.php';

class AdminComunicadoController extends BaseController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'publico' => trim((string)($_GET['publico'] ?? '')),
            'placement' => trim((string)($_GET['placement'] ?? '')),
        ];
        $stats = Comunicado::statsAdmin();
        $items = Comunicado::listAdmin($filters, $page, 20);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/comunicados/index.php';
    }

    public function form(?int $id = null): void
    {
        AuthService::requireAuth('admin');
        $item = $id ? Comunicado::findById($id) : null;
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/comunicados/form.php';
    }

    public function save(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $input = $_POST;
            if (isset($_FILES['imagem_desktop_file']) && ($_FILES['imagem_desktop_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $input['imagem_desktop'] = MediaUploadService::storeCommunicationImage($_FILES['imagem_desktop_file'], 'comunicado_desktop');
            }
            if (isset($_FILES['imagem_mobile_file']) && ($_FILES['imagem_mobile_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $input['imagem_mobile'] = MediaUploadService::storeCommunicationImage($_FILES['imagem_mobile_file'], 'comunicado_mobile');
            }
            $payload = ComunicadoService::validatePayload($input);
            $id = Comunicado::save($payload);
            echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function publish(int $id = 0): void
    {
        $this->setStatus($id, 'publicado');
    }

    public function pause(int $id = 0): void
    {
        $this->setStatus($id, 'pausado');
    }

    public function archive(int $id = 0): void
    {
        $this->setStatus($id, 'arquivado');
    }

    private function setStatus(int $id, string $status): void
    {
        AuthService::requireAuth('admin');
        if ($id <= 0) {
            $this->redirect('/admin/comunicados?erro=id');
        }
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            $this->redirect('/admin/comunicados?erro=csrf');
        }
        Comunicado::setStatus($id, $status, $this->currentUserId());
        $this->redirect('/admin/comunicados?ok=1');
    }

    public function preview(int $id): void
    {
        AuthService::requireAuth('admin');
        $item = Comunicado::findById($id);
        require __DIR__ . '/../Views/admin/comunicados/preview.php';
    }

    public function metrics(int $id): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'metrics' => Comunicado::metricsById($id),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function currentUserId(): int
    {
        $user = AuthService::getCurrentUser();
        return (int)($user['id'] ?? 0);
    }
}
