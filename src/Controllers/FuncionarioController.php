<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/FuncionarioController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/DemandaService.php';
require_once __DIR__ . '/../Models/Demanda.php';

/**
 * Painel do funcionário — financeiro e operacional de cliente/guincho.
 *
 * Regra de ouro deste controller: NENHUMA action aqui executa algo sensível
 * diretamente. Toda ação sensível (cancelamento, conclusão manual,
 * pagamento, alteração de dados, reembolso) vira uma "demanda" pendente via
 * DemandaService::criar() — quem decide de fato é um gerente, em outro
 * controller (GerenteController). Isso é intencional: mesmo que esta conta
 * seja comprometida, o máximo que se consegue é criar pedidos que um
 * gerente vai revisar (e pode rejeitar).
 */
class FuncionarioController extends BaseController
{
    public function dashboard(): void
    {
        $user = AuthService::requireAuth('funcionario');
        $minhas = Demanda::listarPorSolicitante((int)$user['id'], 20);
        $resumo = ['pendente' => 0, 'aprovada_parcial' => 0, 'aprovada' => 0, 'executada' => 0, 'rejeitada' => 0, 'falhou' => 0];
        foreach ($minhas as $d) {
            $st = (string)$d['status'];
            if (isset($resumo[$st])) {
                $resumo[$st]++;
            }
        }
        require __DIR__ . '/../Views/funcionario/dashboard.php';
    }

    public function pedidos(): void
    {
        AuthService::requireAuth('funcionario');
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT p.id, p.status, p.criado_em, p.custo_final, p.custo_estimado,
                    u.nome AS cliente_nome, g.id AS guincho_id, ug.nome AS guincho_nome
               FROM pedidos p
               JOIN usuarios u ON u.id = p.cliente_id
               LEFT JOIN guinchos g ON g.id = p.guincho_id
               LEFT JOIN usuarios ug ON ug.id = g.usuario_id
              ORDER BY p.criado_em DESC
              LIMIT 100"
        );
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/funcionario/pedidos.php';
    }

    public function financeiro(): void
    {
        AuthService::requireAuth('funcionario');
        $pdo = getPDO();

        $jobs = $pdo->query(
            "SELECT pj.id, pj.pedido_id, pj.status, pj.last_error, pj.updated_at,
                    ug.nome AS guincho_nome
               FROM payment_jobs pj
               LEFT JOIN pedidos p ON p.id = pj.pedido_id
               LEFT JOIN guinchos g ON g.id = p.guincho_id
               LEFT JOIN usuarios ug ON ug.id = g.usuario_id
              WHERE pj.status IN ('failed','queued','processing')
              ORDER BY pj.updated_at DESC
              LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        $pagamentos = $pdo->query(
            "SELECT pg.id AS pagamento_id, pg.pedido_id, pg.valor_total, pg.status, p.status AS pedido_status,
                    u.nome AS cliente_nome
               FROM pagamentos pg
               JOIN pedidos p ON p.id = pg.pedido_id
               JOIN usuarios u ON u.id = p.cliente_id
              WHERE pg.status = 'aprovado'
              ORDER BY pg.criado_em DESC
              LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/funcionario/financeiro.php';
    }

    public function demandas(): void
    {
        $user = AuthService::requireAuth('funcionario');
        $demandas = Demanda::listarPorSolicitante((int)$user['id'], 100);
        require __DIR__ . '/../Views/funcionario/demandas.php';
    }

    public function demandaNovaForm(): void
    {
        AuthService::requireAuth('funcionario');
        $tipoPreSelecionado = (string)($_GET['tipo'] ?? '');
        $pedidoIdPre = (int)($_GET['pedido_id'] ?? 0);
        $guinchoIdPre = (int)($_GET['guincho_id'] ?? 0);
        $paymentJobIdPre = (int)($_GET['payment_job_id'] ?? 0);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/funcionario/demandanova.php';
    }

    public function demandaCriar(): void
    {
        $user = AuthService::requireAuth('funcionario');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $tipo = (string)($_POST['tipo'] ?? '');
        $dados = [
            'justificativa' => trim((string)($_POST['justificativa'] ?? '')),
            'pedido_id' => !empty($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : null,
            'guincho_id' => !empty($_POST['guincho_id']) ? (int)$_POST['guincho_id'] : null,
            'payment_job_id' => !empty($_POST['payment_job_id']) ? (int)$_POST['payment_job_id'] : null,
            'valor_envolvido' => isset($_POST['valor_envolvido']) && $_POST['valor_envolvido'] !== '' ? (float)$_POST['valor_envolvido'] : null,
            'campo' => (string)($_POST['campo'] ?? ''),
            'valor_novo' => (string)($_POST['valor_novo'] ?? ''),
        ];

        if ($tipo === 'conclusao_manual') {
            $comprovantes = [];
            foreach (['coleta', 'entrega'] as $t) {
                $key = 'comprovante_' . $t;
                if (!empty($_FILES[$key]) && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $comprovantes[] = ['tipo' => $t, 'file' => $_FILES[$key]];
                }
            }
            $dados['comprovantes'] = $comprovantes;
        }

        $resultado = DemandaService::criar((int)$user['id'], $tipo, $dados);
        if (!$resultado['ok']) {
            $this->setFlashMessage($resultado['erro'], 'error');
            $this->redirect('/funcionario/demandas/nova?tipo=' . urlencode($tipo));
        }

        $this->setFlashMessage('Demanda #' . $resultado['id'] . ' criada — aguardando aprovação de um gerente.', 'success');
        $this->redirect('/funcionario/demandas');
    }
}
