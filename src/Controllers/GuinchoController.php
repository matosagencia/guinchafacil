<?php
// File: guinchafacil/src/Controllers/GuinchoController.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/RankingService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';
require_once __DIR__ . '/../Services/PixService.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Chat.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Avaliacao.php';

/**
 * Controller do perfil Guincho
 */
class GuinchoController extends BaseController
{
    public function __construct(){ parent::__construct(); }

    /**
     * Dashboard: mapa com pedidos disponíveis ao redor
     */
    public function dashboard(): void
    {
        AuthService::requireAuth('guincho');
        $usuario = AuthService::getCurrentUser();
        
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        if (!$guincho) {
            $this->redirect('/login');
        }
        
        // Pedido ativo atual (se houver)
        $pedidoAtivo = Pedido::buscarAtivoDoGuincho($guincho['id']);

        // Variáveis para a view
        $disponivel = (bool)$guincho['disponivel'];

        // Estatísticas do dia
        $pdo = getPDO();
        $stats = $pdo->prepare(
            "SELECT
                COUNT(*) AS atendimentos_hoje,
                COALESCE(SUM(p.custo_final), 0) AS ganho_hoje,
                COALESCE(SUM(p.distancia_km), 0) AS km_hoje
             FROM pedidos p
             WHERE p.guincho_id = ? AND p.status = 'concluido' AND DATE(p.criado_em) = CURDATE()"
        );
        $stats->execute([$guincho['id']]);
        $statsHoje = $stats->fetch(PDO::FETCH_ASSOC);

        $atendimentosHoje = (int)($statsHoje['atendimentos_hoje'] ?? 0);
        $ganhoHoje        = (float)($statsHoje['ganho_hoje'] ?? 0);
        $kmPercorridos    = (float)($statsHoje['km_hoje'] ?? 0);
        $notaMedia        = (float)($guincho['reputacao'] ?? 0);

        $csrfToken = AuthService::gerarCsrfToken();

        // Próximo pedido aguardando (só exibe se o guincho não tiver corrida ativa)
        $pedidoPendente = null;
        if (!$pedidoAtivo && $disponivel) {
            $aguardando = Pedido::listarAguardandoGuincho();
            $pedidoPendente = !empty($aguardando) ? $aguardando[0] : null;
        }

        require __DIR__ . '/../Views/guincho/dashboard.php';
    }

    /**
     * AJAX: atualiza localização do guincho
     */
    public function atualizarLocalizacao(): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'erro' => 'Método inválido']);
            exit;
        }
        
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'erro' => 'Token inválido']);
            exit;
        }
        
        $usuario = AuthService::getCurrentUser();
        $lat = filter_var($_POST['lat'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng'] ?? '', FILTER_VALIDATE_FLOAT);
        
        if ($lat === false || $lng === false) {
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas inválidas']);
            exit;
        }
        
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        Guincho::atualizarLocalizacao($guincho['id'], $lat, $lng);
        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * AJAX: alterna disponibilidade online/offline
     */
    public function toggleDisponibilidade(): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');

        // Aceita tanto FormData (POST) quanto JSON body
        $input = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $raw   = file_get_contents('php://input');
            $input = json_decode($raw, true) ?: [];
        } else {
            $input = $_POST;
        }

        if (!AuthService::validarCsrfToken($input['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'erro' => 'Token inválido']);
            exit;
        }

        $usuario    = AuthService::getCurrentUser();
        $guincho    = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) {
            echo json_encode(['ok' => false, 'erro' => 'Guincho não encontrado']);
            exit;
        }

        // Usa o estado desejado enviado pelo cliente; se não vier, inverte o atual
        // IMPORTANTE: (int) antes do cast — FormData envia "0"/"1" como string,
        // e em PHP a string "0" é falsy, mas qualquer outra string não-vazia é truthy.
        // Usar intval() garante comportamento correto.
        if (isset($input['disponivel'])) {
            $novoStatus = (intval($input['disponivel']) === 1) ? 1 : 0;
        } else {
            $novoStatus = $guincho['disponivel'] ? 0 : 1;
        }

        // Guincho não aprovado não pode se marcar disponível
        if ($novoStatus === 1 && !$guincho['aprovado']) {
            echo json_encode(['ok' => false, 'erro' => 'Seu cadastro ainda não foi aprovado pelo administrador.', 'disponivel' => 0]);
            exit;
        }

        Guincho::atualizarDisponibilidade($guincho['id'], (bool)$novoStatus);
        echo json_encode(['ok' => true, 'disponivel' => $novoStatus]);
        exit;
    }

    /**
     * AJAX: retorna pedidos disponíveis ao redor do guincho
     */
    public function pedidosDisponiveis(): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');
        
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        
        if (!$guincho['disponivel'] || !$guincho['aprovado']) {
            echo json_encode(['ok' => true, 'pedidos' => []]);
            exit;
        }
        
        $lat = (float)($guincho['lat_atual'] ?? -23.5505);
        $lng = (float)($guincho['lng_atual'] ?? -46.6333);
        
        $cfg = Configuracao::getAll();
        $raio = (int)($cfg['raio_maximo_km'] ?? 50);
        
        // Busca pedidos aguardando guincho
        $pedidos = Pedido::listarAguardandoGuincho();
        $resultado = [];
        
        foreach ($pedidos as $pedido) {
            $distancia = RankingService::calcularDistanciaHaversine(
                $lat, $lng,
                (float)$pedido['lat_origem'],
                (float)$pedido['lng_origem']
            );
            
            if ($distancia > $raio) continue;
            
            $score = RankingService::calcularScore($distancia, (float)$guincho['reputacao']);
            if ($score < (float)$pedido['score_minimo_atual']) continue;
            
            $resultado[] = [
                'id'               => $pedido['id'],
                'tipo_problema'    => htmlspecialchars($pedido['tipo_problema']),
                'endereco_origem'  => htmlspecialchars($pedido['endereco_origem']),
                'endereco_destino' => htmlspecialchars($pedido['endereco_destino']),
                'lat_origem'       => $pedido['lat_origem'],
                'lng_origem'       => $pedido['lng_origem'],
                'distancia_km'     => round($distancia, 1),
                'custo_estimado'   => number_format($pedido['custo_estimado'], 2, '.', ''),
                'score'            => round($score, 4),
            ];
        }
        
        // Ordena por score decrescente
        usort($resultado, fn($a, $b) => $b['score'] <=> $a['score']);
        echo json_encode(['ok' => true, 'pedidos' => $resultado]);
        exit;
    }

    /**
     * Exibe detalhe do pedido para o guincho aceitar
     */
    public function aceitarForm(int $id): void
    {
        AuthService::requireAuth('guincho');
        $pedido = Pedido::buscarPorId($id);
        
        if (!$pedido || $pedido['status'] !== 'aguardando_guincho') {
            $this->redirect('/guincho/dashboard');
        }
        
        require __DIR__ . '/../Views/guincho/pedidoaceitar.php';
    }

    /**
     * POST: guincho aceita o pedido
     * §11: SELECT FOR UPDATE previne race condition no aceite simultâneo
     */
    public function aceitar(int $id): void
    {
        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/guincho/dashboard');
        }

        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho || !$guincho['aprovado']) {
            $this->redirect('/guincho/dashboard');
        }

        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            // Bloqueia a linha atomicamente — somente um guincho consegue a transição
            $stmt = $pdo->prepare(
                "SELECT id FROM pedidos WHERE id = ? AND status = 'aguardando_guincho' FOR UPDATE"
            );
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                // Pedido já foi aceito por outro guincho ou não existe
                $pdo->rollBack();
                $this->redirect('/guincho/dashboard');
            }

            Pedido::atribuirGuincho($id, $guincho['id']); // seta status = 'a_caminho'
            Guincho::atualizarDisponibilidade($guincho['id'], false);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[GuinchoController::aceitar] ' . $e->getMessage());
            $this->redirect('/guincho/dashboard');
        }

        // Notifica cliente (fora da transação para não bloquear SMTP)
        $pedido = Pedido::buscarPorId($id);
        if ($pedido) {
            $cliente        = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];
            $guinchoUsuario = ['nome' => $usuario['nome'], 'telefone' => $usuario['telefone'], 'placa_guincho' => $guincho['placa_guincho']];
            NotificacaoService::guinchoAceito($pedido, $cliente, $guinchoUsuario);
        }

        $this->redirect("/guincho/atendimento/{$id}");
    }

    /**
     * GET: formulário de edição de perfil
     */
    public function perfilForm(): void
    {
        AuthService::requireAuth('guincho');
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        if (!$guincho) { $this->redirect('/login'); }

        $pdo    = getPDO();
        $stmt   = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$usuario['id']]);
        $dadosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $csrfToken = AuthService::gerarCsrfToken();
        $flashMsg  = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        require __DIR__ . '/../Views/guincho/perfil.php';
    }

    /**
     * POST: salva edição de perfil
     */
    public function perfilSalvar(): void
    {
        AuthService::requireAuth('guincho');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        $pdo     = getPDO();

        try {
            $pdo->beginTransaction();

            // ── Dados pessoais (tabela usuarios) ─────────────────────
            $nome     = trim($_POST['nome'] ?? '');
            $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');

            if (mb_strlen($nome) < 3) throw new Exception('Nome deve ter ao menos 3 caracteres.');
            if (strlen($telefone) < 10) throw new Exception('Telefone inválido.');

            // Troca de senha (opcional)
            if (!empty($_POST['nova_senha'])) {
                $novaSenha = $_POST['nova_senha'];
                $confirma  = $_POST['confirmar_senha'] ?? '';
                if (strlen($novaSenha) < 6) throw new Exception('Nova senha deve ter ao menos 6 caracteres.');
                if ($novaSenha !== $confirma)  throw new Exception('As senhas não conferem.');

                // Verifica senha atual
                $stmtSenha = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
                $stmtSenha->execute([$usuario['id']]);
                $row = $stmtSenha->fetch(PDO::FETCH_ASSOC);
                if (!password_verify($_POST['senha_atual'] ?? '', $row['senha_hash'] ?? '')) {
                    throw new Exception('Senha atual incorreta.');
                }

                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ?, senha_hash = ? WHERE id = ?")
                    ->execute([$nome, $telefone, password_hash($novaSenha, PASSWORD_BCRYPT), $usuario['id']]);
            } else {
                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ? WHERE id = ?")
                    ->execute([$nome, $telefone, $usuario['id']]);
            }

            // Atualiza nome na sessão
            $_SESSION['user']['nome'] = $nome;

            // ── Dados do veículo / operação (tabela guinchos) ─────────
            $placa        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa_guincho'] ?? ''));
            $capacidade   = (float)($_POST['capacidade_ton']    ?? 0);
            $raio         = (int)($_POST['raio_cobertura_km']   ?? 20);
            $chavePix     = trim($_POST['chave_pix']            ?? '');
            $chaveTipo    = $_POST['chave_pix_tipo']            ?? 'cpf';
            $cnhNumero    = preg_replace('/\D/', '', $_POST['cnh_numero'] ?? '');
            $cnhValidade  = trim($_POST['cnh_validade']         ?? '');

            if (strlen($placa) < 7) throw new Exception('Placa inválida (mínimo 7 caracteres).');
            if ($capacidade <= 0)   throw new Exception('Capacidade deve ser maior que zero.');
            if (empty($chavePix))   throw new Exception('Chave PIX é obrigatória.');

            // Verifica se placa já pertence a outro guincho
            $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? AND id != ?");
            $dupPlaca->execute([$placa, $guincho['id']]);
            if ($dupPlaca->fetch()) throw new Exception('Placa já cadastrada para outro guincho.');

            $pdo->prepare(
                "UPDATE guinchos SET
                    placa_guincho = ?, capacidade_ton = ?, raio_cobertura_km = ?,
                    chave_pix = ?, chave_pix_tipo = ?,
                    cnh_numero = ?, cnh_validade = ?
                 WHERE id = ?"
            )->execute([
                $placa, $capacidade, $raio,
                $chavePix, $chaveTipo,
                $cnhNumero, $cnhValidade,
                $guincho['id']
            ]);

            $pdo->commit();
            $_SESSION['_flash'][] = ['message' => 'Perfil atualizado com sucesso!', 'type' => 'success'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error'];
        }

        $this->redirect('/guincho/perfil');
    }

    /**
     */
    public function recusar(int $id): void
    {
        AuthService::requireAuth('guincho');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/guincho/dashboard');
        }
        // Não altera o pedido — apenas redireciona. Outro guincho pode aceitar.
        $this->redirect('/guincho/dashboard');
    }

    /**
     */
    public function atualizarStatus(int $id): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');
        
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'erro' => 'Token inválido']);
            exit;
        }
        
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        $pedido  = Pedido::buscarPorId($id);
        
        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {
            echo json_encode(['ok' => false, 'erro' => 'Acesso negado']);
            exit;
        }
        
        $transicoes = [
            'a_caminho'  => 'no_local',
            'no_local'   => 'em_reboque',
            'em_reboque' => 'concluido',
        ];
        
        $novoStatus = $transicoes[$pedido['status']] ?? null;
        if (!$novoStatus) {
            echo json_encode(['ok' => false, 'erro' => 'Transição inválida']);
            exit;
        }
        
        Pedido::atualizarStatus($id, $novoStatus);
        
        // Se concluído: libera guincho, dispara Pix, notifica (§4.3)
        if ($novoStatus === 'concluido') {
            Guincho::atualizarDisponibilidade($guincho['id'], true);

            $cfg          = Configuracao::getAll();
            $comissao     = (float)($cfg['comissao_plataforma'] ?? 0.15);
            $valorTotal   = (float)($pedido['custo_final'] ?: $pedido['custo_estimado']);
            $valorPlat    = round($valorTotal * $comissao, 2);
            $valorGuincho = round($valorTotal - $valorPlat, 2);

            $chavePix  = $guincho['chave_pix']      ?? '';
            $chaveTipo = $guincho['chave_pix_tipo'] ?? 'EVP';

            // Marca status_pix=processando antes de chamar API
            $pdo = getPDO();
            $pdo->prepare(
                "UPDATE pagamentos
                 SET valor_guincho = ?, valor_plataforma = ?, status_pix = 'processando'
                 WHERE pedido_id = ? AND status = 'aprovado'"
            )->execute([$valorGuincho, $valorPlat, $id]);

            // Transferência Pix real (§4.3)
            $pix = PixService::transferir($id, $valorGuincho, $chavePix, $chaveTipo);

            if ($pix['sucesso']) {
                // §4.3: registrar id_transacao_pix e marcar pago_guincho=1 só após confirmação
                $pdo->prepare(
                    "UPDATE pagamentos
                     SET id_transacao_pix = ?, status_pix = 'concluido',
                         pago_guincho = 1, data_pagamento_guincho = NOW()
                     WHERE pedido_id = ? AND status = 'aprovado'"
                )->execute([$pix['id_transacao'], $id]);
            } else {
                // Falha: status_pix=falha para reprocessamento posterior (§4.3 + §11)
                $pdo->prepare(
                    "UPDATE pagamentos SET status_pix = 'falha', pago_guincho = 0
                     WHERE pedido_id = ? AND status = 'aprovado'"
                )->execute([$id]);
                error_log("[GuinchoController] Falha Pix pedido {$id}: " . $pix['erro']);

                // Notifica admin (§4.3 + §9)
                $adminRow = $pdo->query("SELECT email FROM usuarios WHERE tipo='admin' AND ativo=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($adminRow) {
                    NotificacaoService::falhaPixAdmin($pedido, $pix['erro'] ?? 'Erro desconhecido');
                }
            }

            $cliente     = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];
            $guinchoUser = ['nome' => $usuario['nome'], 'email' => $usuario['email']];
            $pedido['custo_final'] = $valorTotal;
            NotificacaoService::pedidoConcluido($pedido, $cliente, $guinchoUser, $valorGuincho);
        }
        
        echo json_encode(['ok' => true, 'status' => $novoStatus]);
        exit;
    }

    /**
     * Tela de atendimento em andamento
     */
    public function atendimento(int $id): void
    {
        AuthService::requireAuth('guincho');
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        $pedido  = Pedido::buscarPorId($id);
        
        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {
            $this->redirect('/guincho/dashboard');
        }
        
        $mensagens = Chat::listarPorPedido($id);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/guincho/atendimento.php';
    }

    /**
     * AJAX: envia mensagem no chat
     */
    public function chatEnviar(int $pedidoId): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');
        
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'erro' => 'Token inválido']);
            exit;
        }
        
        $usuario = AuthService::getCurrentUser();
        $mensagem = trim($_POST['mensagem'] ?? '');
        
        if (empty($mensagem) || mb_strlen($mensagem) > 1000) {
            echo json_encode(['ok' => false, 'erro' => 'Mensagem inválida']);
            exit;
        }
        
        $msgId = Chat::enviar($pedidoId, $usuario['id'], $mensagem);
        echo json_encode(['ok' => true, 'id' => $msgId]);
        exit;
    }

    /**
     * AJAX: busca mensagens novas do chat
     */
    public function chatMensagens(int $pedidoId): void
    {
        AuthService::requireAuth('guincho');
        header('Content-Type: application/json');
        
        $usuario  = AuthService::getCurrentUser();
        $desdeId  = (int)($_GET['desde_id'] ?? 0);
        $mensagens = Chat::listarPorPedido($pedidoId, $desdeId);
        
        Chat::marcarLidas($pedidoId, $usuario['id']);
        echo json_encode(['ok' => true, 'mensagens' => $mensagens]);
        exit;
    }

    /**
     * Histórico de atendimentos do guincho
     */
    public function historico(): void
    {
        AuthService::requireAuth('guincho');
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $pedidos = Pedido::listarPorGuincho($guincho['id'], $pagina);
        $total   = Pedido::contarPorGuincho($guincho['id']);
        require __DIR__ . '/../Views/guincho/historico.php';
    }

    /**
     * Extrato financeiro do guincho
     */
    public function financeiro(): void
    {
        AuthService::requireAuth('guincho');
        $usuario = AuthService::getCurrentUser();
        $guincho = Guincho::buscarPorUsuario($usuario['id']);
        
        $mes  = (int)($_GET['mes'] ?? date('m'));
        $ano  = (int)($_GET['ano'] ?? date('Y'));
        
        $pagamentos = Pagamento::listarPorGuincho($guincho['id'], $mes, $ano);
        $totais     = Pagamento::totaisPorGuincho($guincho['id'], $mes, $ano);

        // Lê comissão real do banco em vez de hardcodar 15%
        $cfg             = Configuracao::getAll();
        $comissaoPercent = (float)($cfg['comissao_plataforma'] ?? 0.15) * 100; // ex: 0.15 → 15

        require __DIR__ . '/../Views/guincho/financeiro.php';
    }
}
