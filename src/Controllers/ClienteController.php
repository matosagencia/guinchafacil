<?php
// File: guinchafacil/src/Controllers/ClienteController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Veiculo.php';
require_once __DIR__ . '/../Models/Oficina.php';
require_once __DIR__ . '/../Models/Chat.php';
require_once __DIR__ . '/../Models/Avaliacao.php';
require_once __DIR__ . '/../Models/Configuracao.php';

class ClienteController extends BaseController
{
    public function __construct() { parent::__construct(); }

    private function usuarioId(): int
    {
        $u = AuthService::getCurrentUser();
        return (int)($u['id'] ?? 0);
    }

    // ─── DASHBOARD ────────────────────────────────────────────────
    public function dashboard(): void
    {
        AuthService::requireAuth('cliente');
        $uid = $this->usuarioId();
        $pedidosRecentes   = Pedido::listarPorCliente($uid, 1, 5);

        // Contagens eficientes via queries diretas ao invés de buscar todos os registros
        $pdo = getPDO();
        $stmtCount = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'concluido') AS concluidos,
                SUM(status IN ('aguardando_guincho','a_caminho','no_local','em_reboque')) AS ativos
             FROM pedidos WHERE cliente_id = ?"
        );
        $stmtCount->execute([$uid]);
        $counts = $stmtCount->fetch(PDO::FETCH_ASSOC);

        $totalPedidos      = (int)($counts['total']     ?? 0);
        $pedidosConcluidos = (int)($counts['concluidos'] ?? 0);
        $pedidosAtivos     = (int)($counts['ativos']     ?? 0);

        $totalVeiculos = count(Veiculo::listarPorUsuario($uid));
        $pedidoAndamento = null;
        foreach ($pedidosRecentes as $p) {
            if (in_array($p['status'], ['aguardando_guincho','a_caminho','no_local','em_reboque'])) {
                $pedidoAndamento = $p; break;
            }
        }
        require __DIR__ . '/../Views/cliente/dashboard.php';
    }

    // ─── VEÍCULOS ─────────────────────────────────────────────────
    public function veiculos(): void
    {
        AuthService::requireAuth('cliente');
        $uid     = $this->usuarioId();
        $veiculos = Veiculo::listarPorUsuario($uid);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/veiculos.php';
    }

    public function veiculoForm(): void
    {
        AuthService::requireAuth('cliente');
        $uid      = $this->usuarioId();
        $veiculo  = null;
        $vid      = (int)($_GET['id'] ?? 0);
        if ($vid > 0) {
            $v = Veiculo::buscarPorId($vid);
            if ($v && (int)$v['usuario_id'] === $uid) $veiculo = $v;
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/veiculoform.php';
    }

    public function veiculoEditar(int $id): void
    {
        AuthService::requireAuth('cliente');
        $uid     = $this->usuarioId();
        $veiculo = Veiculo::buscarPorId($id);
        if (!$veiculo || (int)$veiculo['usuario_id'] !== $uid) {
            $this->redirect('/cliente/veiculos');
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/veiculoform.php';
    }

    public function veiculoSalvar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid   = $this->usuarioId();
        $vid   = (int)($_POST['id'] ?? 0);
        $dados = [
            'placa'  => strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['placa'] ?? ''))),
            'marca'  => trim($_POST['marca']  ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            'ano'    => (int)($_POST['ano']   ?? 0),
            'cor'    => trim($_POST['cor']    ?? ''),
            'tipo'   => in_array($_POST['tipo'] ?? '', ['carro','moto','caminhao','van']) ? $_POST['tipo'] : 'carro',
        ];
        if (empty($dados['marca']) || empty($dados['modelo']) || $dados['ano'] < 1950) {
            $this->redirect('/cliente/veiculo/novo?erro=1');
        }
        if ($vid > 0) {
            $v = Veiculo::buscarPorId($vid);
            if (!$v || (int)$v['usuario_id'] !== $uid) { $this->redirect('/cliente/veiculos'); }
            Veiculo::atualizar($vid, $dados);
        } else {
            Veiculo::criar($uid, $dados);
        }
        $this->redirect('/cliente/veiculos?salvo=1');
    }

    public function veiculoDeletar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid = $this->usuarioId();
        $vid = (int)($_POST['id'] ?? 0);
        $v   = Veiculo::buscarPorId($vid);
        if ($v && (int)$v['usuario_id'] === $uid) {
            Veiculo::desativar($vid);
        }
        $this->redirect('/cliente/veiculos?deletado=1');
    }

    // ─── OFICINAS ─────────────────────────────────────────────────
    public function oficinas(): void
    {
        AuthService::requireAuth('cliente');
        $uid     = $this->usuarioId();
        $oficinas = Oficina::listarPorUsuario($uid);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/oficinas.php';
    }

    public function oficinaForm(): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $oficina = null;
        $oid    = (int)($_GET['id'] ?? 0);
        if ($oid > 0) {
            $o = Oficina::buscarPorId($oid);
            if ($o && (int)$o['usuario_id'] === $uid) $oficina = $o;
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/oficinaform.php';
    }

    public function oficinaEditar(int $id): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $oficina = Oficina::buscarPorId($id);
        if (!$oficina || (int)$oficina['usuario_id'] !== $uid) {
            $this->redirect('/cliente/oficinas');
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/oficinaform.php';
    }

    public function oficinaSalvar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid  = $this->usuarioId();
        $oid  = (int)($_POST['id'] ?? 0);
        $dados = [
            'usuario_id' => $uid,
            'nome'       => trim($_POST['nome']     ?? ''),
            'telefone'   => preg_replace('/\D/', '', $_POST['telefone'] ?? ''),
            'endereco'   => trim($_POST['endereco'] ?? ''),
            'lat'        => (float)($_POST['lat'] ?? 0) ?: null,
            'lng'        => (float)($_POST['lng'] ?? 0) ?: null,
        ];
        if (empty($dados['nome']) || empty($dados['endereco'])) {
            $this->redirect('/cliente/oficina/nova?erro=1');
        }
        if ($oid > 0) {
            $o = Oficina::buscarPorId($oid);
            if (!$o || (int)$o['usuario_id'] !== $uid) { $this->redirect('/cliente/oficinas'); }
            Oficina::atualizar($oid, $dados);
        } else {
            Oficina::criar($dados);
        }
        $this->redirect('/cliente/oficinas?salvo=1');
    }

    public function oficinaDeletar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid = $this->usuarioId();
        $oid = (int)($_POST['id'] ?? 0);
        $o   = Oficina::buscarPorId($oid);
        if ($o && (int)$o['usuario_id'] === $uid) {
            Oficina::deletar($oid);
        }
        $this->redirect('/cliente/oficinas?deletado=1');
    }

    // ─── PEDIDOS ──────────────────────────────────────────────────
    public function historico(): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $pedidos      = Pedido::listarPorCliente($uid, $pagina, 20);
        $total        = Pedido::contarPorCliente($uid);
        $totalPaginas = (int)ceil($total / 20);

        require __DIR__ . '/../Views/cliente/historico.php';
    }

    public function pedidoNovo(): void
    {
        AuthService::requireAuth('cliente');
        $uid     = $this->usuarioId();
        $veiculos = Veiculo::listarPorUsuario($uid);
        $oficinas = Oficina::listarPorUsuario($uid);
        $cfg      = Configuracao::getAll();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/pedidonovo.php';
    }

    public function pedidoCriar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid       = $this->usuarioId();
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $tipo      = $_POST['tipo_problema'] ?? 'outro';
        $descricao = trim($_POST['descricao'] ?? '');
        $endOrigem = trim($_POST['endereco_origem'] ?? '');
        $latOrigem = (float)($_POST['lat_origem'] ?? 0);
        $lngOrigem = (float)($_POST['lng_origem'] ?? 0);
        $endDest   = trim($_POST['endereco_destino'] ?? '');
        $latDest   = (float)($_POST['lat_destino'] ?? 0);
        $lngDest   = (float)($_POST['lng_destino'] ?? 0);
        $distancia = (float)($_POST['distancia_km'] ?? 5);

        // Validar que veículo pertence ao cliente
        $veiculo = Veiculo::buscarPorId($veiculoId);
        if (!$veiculo || (int)$veiculo['usuario_id'] !== $uid) {
            $this->redirect('/cliente/pedido/novo?erro=veiculo');
        }

        $tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];
        if (!in_array($tipo, $tiposValidos)) $tipo = 'outro';

        $cfg   = Configuracao::getAll();
        $custo = round((float)($cfg['tarifa_por_km'] ?? 5) * $distancia + (float)($cfg['taxa_fixa'] ?? 10), 2);

        // §7: coordenadas obrigatórias e dentro dos limites do Brasil (lat [-34,5], lng [-74,-28])
        $latValida = static fn(float $v): bool => $v >= -34 && $v <= 5;
        $lngValida = static fn(float $v): bool => $v >= -74 && $v <= -28;

        if ($latOrigem === 0.0 || $lngOrigem === 0.0 || !$latValida($latOrigem) || !$lngValida($lngOrigem)) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_origem');
        }
        if ($latDest === 0.0 || $lngDest === 0.0 || !$latValida($latDest) || !$lngValida($lngDest)) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_destino');
        }
        if (empty($endOrigem)) $endOrigem = 'Localização atual';
        if (empty($endDest))   $endDest   = 'Destino informado';

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (cliente_id, veiculo_id, tipo_problema, descricao_problema,
             lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
             distancia_km, custo_estimado, status, raio_atual_km, score_minimo_atual,
             expiracao_aceite, criado_em)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'aguardando_pagamento',10,0.5000,
             DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())"
        );
        $stmt->execute([
            $uid, $veiculoId, $tipo, $descricao,
            $latOrigem, $lngOrigem, $endOrigem,
            $latDest,   $lngDest,   $endDest,
            $distancia, $custo,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();
        // Redireciona para checkout de pagamento
        $this->redirect("/pagamento/checkout/{$pedidoId}");
    }

    // ─── CANCELAMENTO DE PEDIDO ───────────────────────────────────
    /**
     * POST /cliente/cancelar/{id}
     * §3: cliente só pode cancelar em aguardando_pagamento ou aguardando_guincho
     * §4.4: estorno automático é disparado pelo PedidoService
     */
    public function cancelarPedido(int $id): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid = $this->usuarioId();

        require_once __DIR__ . '/../Services/PedidoService.php';
        $pedidoService = new PedidoService();
        $cancelado = $pedidoService->cancel($id, $uid);

        if ($cancelado) {
            $this->redirect('/cliente/historico?cancelado=1');
        } else {
            $this->redirect("/cliente/pedido/{$id}?erro=cancelamento_negado");
        }
    }

    public function pedidoStatus(int $id): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            $this->redirect('/cliente/historico');
        }
        $mensagens = Chat::listarPorPedido($id);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/pedidostatus.php';
    }

    public function pedidoStatusAjax(int $id): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json');
        $uid    = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            echo json_encode(['ok' => false]); exit;
        }
        echo json_encode([
            'ok'              => true,
            'status'          => $pedido['status'],
            'guincho_nome'    => $pedido['guincho_operador'] ?? null,
            'guincho_placa'   => $pedido['guincho_placa']   ?? null,
            'guincho_tel'     => $pedido['guincho_telefone'] ?? null,
            'lat_guincho'     => $pedido['lat_guincho']      ?? null,
            'lng_guincho'     => $pedido['lng_guincho']      ?? null,
        ]);
        exit;
    }

    public function calcularCusto(): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json');
        $distancia = (float)($_GET['distancia_km'] ?? 0);
        if ($distancia <= 0) {
            echo json_encode(['ok' => false, 'erro' => 'Distância inválida']); exit;
        }
        $cfg   = Configuracao::getAll();
        $custo = round((float)($cfg['tarifa_por_km'] ?? 5) * $distancia + (float)($cfg['taxa_fixa'] ?? 10), 2);
        echo json_encode(['ok' => true, 'custo' => $custo, 'distancia' => $distancia]);
        exit;
    }

    // ─── CHAT ─────────────────────────────────────────────────────
    public function chat(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            $this->redirect('/cliente/historico'); exit;
        }
        $mensagens = Chat::listarPorPedido($pedidoId);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/pedidostatus.php';
    }

    public function chatEnviar(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'erro' => 'Token inválido']); exit;
        }
        $uid      = $this->usuarioId();
        $pedido   = Pedido::buscarPorId($pedidoId);
        $mensagem = trim($_POST['mensagem'] ?? '');
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid || empty($mensagem)) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']); exit;
        }
        $msgId = Chat::enviar($pedidoId, $uid, $mensagem);
        echo json_encode(['ok' => true, 'id' => $msgId]);
        exit;
    }

    public function chatMensagens(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json');
        $uid      = $this->usuarioId();
        $pedido   = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            echo json_encode(['ok' => false]); exit;
        }
        $desdeId   = (int)($_GET['desde_id'] ?? 0);
        $mensagens = Chat::listarPorPedido($pedidoId, $desdeId);
        Chat::marcarLidas($pedidoId, $uid);
        echo json_encode(['ok' => true, 'mensagens' => $mensagens]);
        exit;
    }

    // ─── AVALIAÇÃO ────────────────────────────────────────────────
    public function avaliar(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $uid    = $this->usuarioId();
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid || $pedido['status'] !== 'concluido') {
            $this->redirect('/cliente/historico');
        }
        $jaAvaliou = Avaliacao::jaAvaliou($pedidoId, $uid);
        if ($jaAvaliou) { $this->redirect('/cliente/historico?ja_avaliou=1'); }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/avaliacao.php';
    }

    public function avaliarSalvar(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $uid      = $this->usuarioId();
        $pedido   = Pedido::buscarPorId($pedidoId);
        $estrelas = (int)($_POST['estrelas'] ?? 0);
        $coment   = trim($_POST['comentario'] ?? '');
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid
            || $pedido['status'] !== 'concluido'
            || $estrelas < 1 || $estrelas > 5) {
            $this->redirect('/cliente/historico');
        }
        $guinchoId = (int)($pedido['guincho_id'] ?? 0);
        if ($guinchoId) {
            // Busca o usuario_id do guincho para usar na FK de avaliacoes (references guinchos.id agora)
            Avaliacao::criar($pedidoId, $uid, $guinchoId, $estrelas, $coment);
            // Recalcula reputação
            require_once __DIR__ . '/../Models/Guincho.php';
            Guincho::atualizarReputacao($guinchoId);
        }
        $this->redirect('/cliente/historico?avaliado=1');
    }
}
