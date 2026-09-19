<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/PushActionTokenService.php';
require_once __DIR__ . '/../Services/EspecialistaAtendimentoService.php';
require_once __DIR__ . '/../Models/Especialista.php';

class PushActionController extends BaseController
{
    public function aceitar(int $id = 0): void
    {
        $this->processar($id, 'aceitar');
    }

    public function recusar(int $id = 0): void
    {
        $this->processar($id, 'recusar');
    }

    private function processar(int $atendimentoId, string $acao): void
    {
        $input = $this->requestData();
        $token = (string)($input['token'] ?? $input['action_token'] ?? '');
        if ($token === '') {
            $this->json(['ok' => false, 'erro' => 'token_obrigatorio'], 422);
        }

        try {
            $claims = PushActionTokenService::validar($token, $acao);
            $especialista = Especialista::buscarPorUsuarioId((int)($claims['uid'] ?? 0));
            if (!$especialista || (int)$especialista['id'] !== (int)($claims['esp_id'] ?? 0)) {
                $this->json(['ok' => false, 'erro' => 'especialista_invalido'], 403);
            }
            if ((int)($claims['aid'] ?? 0) !== $atendimentoId) {
                $this->json(['ok' => false, 'erro' => 'atendimento_invalido'], 403);
            }

            if ($acao === 'aceitar') {
                EspecialistaAtendimentoService::aceitar($atendimentoId, (int)$especialista['id']);
            } else {
                EspecialistaAtendimentoService::transicionar($atendimentoId, (int)$especialista['id'], 'procurando', ['ofertado']);
            }

            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'action',
                'code' => 'PUSH-020',
                'message' => 'Ação do push processada com sucesso.',
                'context' => [
                    'atendimento_id' => $atendimentoId,
                    'acao' => $acao,
                    'usuario_id' => (int)$claims['uid'],
                    'especialista_id' => (int)$especialista['id'],
                ],
            ]);

            $isHtmlGet = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')
                && strpos(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') === false;
            if ($isHtmlGet) {
                $this->redirect('/especialista/dashboard?push=1&acao=' . rawurlencode($acao) . '&atendimento_id=' . $atendimentoId);
            }

            $this->json(['ok' => true, 'acao' => $acao, 'atendimento_id' => $atendimentoId]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    private function requestData(): array
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            return is_array($decoded) ? $decoded : [];
        }
        return array_merge($_GET, $_POST);
    }

    private function json(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
