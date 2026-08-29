<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/GerenteController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/DemandaService.php';
require_once __DIR__ . '/../Models/Demanda.php';

/**
 * Painel do gerente — a única porta de saída pra qualquer demanda criada
 * por um funcionário virar ação real no sistema (ver DemandaService).
 *
 * Controles de segurança aplicados aqui, além dos já embutidos no service:
 *   - decisão exige senha da própria conta (mesmo padrão usado pelo admin em
 *     ações sensíveis como cancelamento e conclusão manual);
 *   - nota da decisão é obrigatória tanto pra aprovar quanto pra rejeitar;
 *   - o service já bloqueia um gerente decidir a própria demanda e exige um
 *     segundo gerente DIFERENTE quando há dupla aprovação — aqui só
 *     garantimos que ninguém chega no service sem senha confirmada.
 */
class GerenteController extends BaseController
{
    public function dashboard(): void
    {
        AuthService::requireAuth('gerente');
        $pendentes = Demanda::listarPendentes();
        $resumo = ['pendente' => 0, 'aprovada_parcial' => 0];
        foreach ($pendentes as $d) {
            $st = (string)$d['status'];
            if (isset($resumo[$st])) {
                $resumo[$st]++;
            }
        }
        $antigas = Demanda::contarPendentesAntigas(24);
        require __DIR__ . '/../Views/gerente/dashboard.php';
    }

    public function demandas(): void
    {
        AuthService::requireAuth('gerente');
        $pendentes = Demanda::listarPendentes();
        require __DIR__ . '/../Views/gerente/demandas.php';
    }

    public function demandaDetalhe(int $id): void
    {
        AuthService::requireAuth('gerente');
        $demanda = Demanda::buscar($id);
        if (!$demanda) {
            http_response_code(404);
            echo 'Demanda não encontrada.';
            return;
        }
        $payload = json_decode((string)($demanda['payload_json'] ?? '{}'), true) ?: [];
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/gerente/demandadetalhe.php';
    }

    public function demandaDecidir(): void
    {
        $user = AuthService::requireAuth('gerente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $demandaId = (int)($_POST['demanda_id'] ?? 0);
        $veredito = (string)($_POST['veredito'] ?? '');
        $nota = trim((string)($_POST['nota'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        if (!$demandaId || $senha === '' || $nota === '') {
            $this->setFlashMessage('Dados incompletos: nota e senha são obrigatórias.', 'error');
            $this->redirect("/gerente/demanda/{$demandaId}");
        }

        $stmt = getPDO()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($senha, $row['senha_hash'])) {
            $this->setFlashMessage('Senha incorreta.', 'error');
            $this->redirect("/gerente/demanda/{$demandaId}");
        }

        $resultado = DemandaService::decidir($demandaId, (int)$user['id'], $veredito, $nota);
        if (!$resultado['ok']) {
            $this->setFlashMessage($resultado['erro'], 'error');
            $this->redirect("/gerente/demanda/{$demandaId}");
        }

        $this->setFlashMessage($resultado['mensagem'] ?? 'Decisão registrada com sucesso.', 'success');
        $this->redirect('/gerente/demandas');
    }
}
