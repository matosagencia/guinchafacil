<?php
// File: guinchafacil/src/Controllers/AdminController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Chat.php';
require_once __DIR__ . '/../Services/Logger.php';

/**
 * Controller do painel administrativo
 * Admin pode acessar e gerenciar TODOS os perfis.
 */
class AdminController extends BaseController
{
    public function __construct() { parent::__construct(); }

    // ─── DASHBOARD ───────────────────────────────────────────────
    public function dashboard(): void
    {
        AuthService::requireAuth('admin');
        $totaisDia     = Pedido::totaisDoDia();
        $ultPedidos    = Pedido::listarRecentes(10);
        $guinchoOnline = Guincho::contarDisponiveis();
        $totalUsuarios = Usuario::contarTotal([]);
        require __DIR__ . '/../Views/admin/dashboard.php';
    }

    // ─── USUÁRIOS ────────────────────────────────────────────────
    public function usuarios(): void
    {
        AuthService::requireAuth('admin');
        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'busca' => $_GET['busca'] ?? '',
            'tipo'  => $_GET['tipo']  ?? '',
        ];
        $usuarios = Usuario::listar($filtros, $pagina);
        $total    = Usuario::contarTotal($filtros);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/usuarios.php';
    }

    public function usuarioDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $usuario = Usuario::buscarPorId($id);
        if (!$usuario) { http_response_code(404); echo '<h1>404</h1>'; exit; }

        // Dados extras por tipo
        $extra = null;
        if ($usuario['tipo'] === 'guincho') {
            $extra = Guincho::buscarPorUsuario($usuario['id']);
        }
        $pedidos = [];
        try {
            if ($usuario['tipo'] === 'cliente') {
                $pedidos = Pedido::listarPorCliente($usuario['id'], 1, 10);
            } elseif ($usuario['tipo'] === 'guincho' && !empty($extra['id'])) {
                $pedidos = Pedido::listarPorGuincho($extra['id'], 1, 10);
            }
        } catch (Throwable $e) { $pedidos = []; }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/usuariodetalhe.php';
    }

    public function usuarioForm(): void
    {
        AuthService::requireAuth('admin');
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/usuarioform.php';
    }

    public function usuarioSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $nome  = trim($_POST['nome']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $tipo  = in_array($_POST['tipo'] ?? '', ['admin','cliente','guincho'])
                    ? $_POST['tipo'] : 'cliente';
        $tel   = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
        $cpf   = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
            $this->redirect('/admin/usuario/novo?erro=1');
        }

        // Verificar duplicatas
        $pdo = getPDO();
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email=? OR cpf=? LIMIT 1");
        $existe->execute([$email, $cpf]);
        if ($existe->fetch()) {
            $this->redirect('/admin/usuario/novo?erro=email_duplicado');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em)
                                VALUES (?,?,?,?,?,?,1,NOW())");
        $stmt->execute([$nome, $email, $hash, $tel, $cpf, $tipo]);
        $usuarioId = (int)$pdo->lastInsertId();
        if ($tipo === 'guincho') {
            $gDados = [
                'cnh_numero'        => trim($_POST['cnh_numero'] ?? ''),
                'cnh_validade'      => $_POST['cnh_validade'] ?? date('Y-m-d', strtotime('+5 years')),
                'placa_guincho'     => strtoupper(trim($_POST['placa_guincho'] ?? 'AAA0000')),
                'capacidade_ton'    => (float)($_POST['capacidade_ton'] ?? 1),
                'raio_cobertura_km' => (int)($_POST['raio_cobertura_km'] ?? 20),
                'chave_pix'         => trim($_POST['chave_pix'] ?? ''),
                'chave_pix_tipo'    => $_POST['chave_pix_tipo'] ?? 'cpf',
                'foto_veiculo'      => $this->processarUpload('foto_veiculo'),
                'doc_cnh_frente'    => $this->processarUpload('doc_cnh_frente'),
                'doc_cnh_verso'     => $this->processarUpload('doc_cnh_verso'),
            ];
            Guincho::criarDeRegistro($usuarioId, $gDados);
        }
        $this->redirect('/admin/usuarios?criado=1');
    }

    public function usuarioAtivar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Usuario::ativar($id);
        $this->redirect('/admin/usuarios?msg=ativado');
    }

    public function usuarioSuspender(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Usuario::suspender($id);
        $this->redirect('/admin/usuarios?msg=suspenso');
    }

    // ─── GUINCHOS ────────────────────────────────────────────────
    public function guinchosPendentes(): void
    {
        AuthService::requireAuth('admin');
        $pendentes = Guincho::listarPendentes();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/guinchospendentes.php';
    }

    public function guinchoDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $guincho = Guincho::buscarPorId($id);
        if (!$guincho) { http_response_code(404); echo '<h1>404</h1>'; exit; }
        $usuario = Usuario::buscarPorId($guincho['usuario_id']);
        $pedidos = Pedido::listarPorGuincho($id, 1, 15);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/guinhodetalhe.php';
    }

    public function guinchoAprovar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Guincho::aprovar($id);
        $guincho = Guincho::buscarPorId($id);
        if ($guincho) {
            $usuario = Usuario::buscarPorId($guincho['usuario_id']);
            if ($usuario) {
                require_once __DIR__ . '/../Services/NotificacaoService.php';
                NotificacaoService::cadastroAprovado($usuario);
            }
        }
        $this->redirect('/admin/guinchos?msg=aprovado');
    }

    public function guinchoRejeitar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        // Marca guincho como rejeitado e suspende usuário
        $pdo = getPDO();
        $g = Guincho::buscarPorId($id);
        if ($g) {
            $pdo->prepare("UPDATE guinchos SET aprovado=0 WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=?")->execute([$g['usuario_id']]);
        }
        $this->redirect('/admin/guinchos?msg=rejeitado');
    }

    // ─── PEDIDOS ─────────────────────────────────────────────────
    public function pedidos(): void
    {
        AuthService::requireAuth('admin');
        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'status' => $_GET['status'] ?? '',
            'busca'  => $_GET['busca']  ?? '',
            'data'   => $_GET['data']   ?? '',
        ];
        $pedidos = Pedido::listarPorStatus((string)($filtros['status'] ?? ''), $pagina, $filtros);
        $total   = Pedido::contar($filtros);
        $totalPaginas = (int)ceil($total / 50);
        require __DIR__ . '/../Views/admin/pedidos.php';
    }

    public function pedidoDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) { http_response_code(404); echo '<h1>404 — Pedido não encontrado</h1>'; exit; }
        $mensagens = Chat::listarPorPedido($id);
        $csrfToken = AuthService::gerarCsrfToken();
        // Guinchos disponíveis para atribuição
        $guinchoDisponiveis = [];
        if (empty($pedido['guincho_id'])) {
            $guinchoDisponiveis = Guincho::listarAprovados();
        }
        require __DIR__ . '/../Views/admin/pedidodetalhe.php';
    }

    public function pedidoCriarForm(): void
    {
        AuthService::requireAuth('admin');
        $pdo      = getPDO();
        $clientes = $pdo->query(
            "SELECT u.id, u.nome, u.email FROM usuarios u WHERE u.tipo='cliente' AND u.ativo=1 ORDER BY u.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
        $veiculos = [];   // carregados via AJAX ao selecionar cliente
        $guinchos = $pdo->query(
            "SELECT g.id, u.nome, g.placa_guincho FROM guinchos g JOIN usuarios u ON g.usuario_id=u.id WHERE g.aprovado=1 AND g.disponivel=1 AND u.ativo=1 ORDER BY u.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
        $cfg      = Configuracao::getAll();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/pedidocriar.php';
    }

    public function pedidoCriar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $pdo         = getPDO();
        $clienteId   = (int)($_POST['cliente_id']   ?? 0);
        $veiculoId   = (int)($_POST['veiculo_id']   ?? 0);
        $guinchoId   = (int)($_POST['guincho_id']   ?? 0) ?: null;
        $tipo        = $_POST['tipo_problema'] ?? 'outro';
        $descricao   = trim($_POST['descricao'] ?? '');
        $endOrigem   = trim($_POST['endereco_origem']  ?? '');
        $endDestino  = trim($_POST['endereco_destino'] ?? '');
        $latOrigem   = (float)($_POST['lat_origem']  ?? -23.5505);
        $lngOrigem   = (float)($_POST['lng_origem']  ?? -46.6333);
        $latDestino  = (float)($_POST['lat_destino'] ?? -23.5505);
        $lngDestino  = (float)($_POST['lng_destino'] ?? -46.6333);

        // Cálculo básico de custo
        $cfg  = Configuracao::getAll();
        $dist = (float)($_POST['distancia_km'] ?? 5);
        $custo = round((float)($cfg['tarifa_por_km'] ?? 5) * $dist + (float)($cfg['taxa_fixa'] ?? 10), 2);

        $tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];
        if (!in_array($tipo, $tiposValidos)) $tipo = 'outro';

        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (cliente_id,veiculo_id,guincho_id,tipo_problema,descricao_problema,
              lat_origem,lng_origem,endereco_origem,lat_destino,lng_destino,endereco_destino,
              distancia_km,custo_estimado,status,criado_em)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'aguardando_guincho',NOW())"
        );
        $stmt->execute([
            $clienteId, $veiculoId, $guinchoId, $tipo, $descricao,
            $latOrigem, $lngOrigem, $endOrigem,
            $latDestino, $lngDestino, $endDestino,
            $dist, $custo,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();

        // Se guincho foi selecionado, já atribui
        if ($guinchoId) {
            $pdo->prepare("UPDATE pedidos SET status='a_caminho' WHERE id=?")->execute([$pedidoId]);
        }

        $this->redirect("/admin/pedido/{$pedidoId}?criado=1");
    }

    // ─── FINANCEIRO ──────────────────────────────────────────────
    public function financeiro(): void
    {
        AuthService::requireAuth('admin');
        $dataInicio = $_GET['inicio'] ?? date('Y-m-01');
        $dataFim    = $_GET['fim']    ?? date('Y-m-d');
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $pagamentos = Pagamento::listar(['inicio' => $dataInicio, 'fim' => $dataFim], $pagina);
        $totais     = Pagamento::totalPorPeriodo($dataInicio, $dataFim);
        require __DIR__ . '/../Views/admin/financeiro.php';
    }

    public function exportarCsv(): void
    {
        AuthService::requireAuth('admin');
        $dataInicio = $_GET['inicio'] ?? date('Y-m-01');
        $dataFim    = $_GET['fim']    ?? date('Y-m-d');
        $pagamentos = Pagamento::listar(['inicio' => $dataInicio, 'fim' => $dataFim], 1, 9999);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="financeiro_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Pedido', 'Método', 'Valor Total', 'Valor Guincho', 'Comissão', 'Status', 'Data'], ';');
        foreach ($pagamentos as $p) {
            fputcsv($out, [
                $p['id'], $p['pedido_id'], $p['metodo'],
                number_format($p['valor_total'],     2, ',', '.'),
                number_format($p['valor_guincho'],   2, ',', '.'),
                number_format($p['valor_plataforma'],2, ',', '.'),
                $p['status'], $p['data_pagamento'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ─── CONFIGURAÇÕES ───────────────────────────────────────────
    public function configuracoes(): void
    {
        AuthService::requireAuth('admin');
        $config    = Configuracao::getAll();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/configuracoes.php';
    }

    public function configuracoesSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $campos = ['tarifa_por_km','taxa_fixa','comissao_plataforma','tempo_expiracao_min','raio_inicial_km','raio_maximo_km'];
        foreach ($campos as $c) {
            if (isset($_POST[$c])) {
                Configuracao::set($c, filter_var($_POST[$c], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }
        }
        // §GW-ADMIN-01: persiste escolha de gateway de pagamento
        if (isset($_POST['gateway_pagamento']) && in_array($_POST['gateway_pagamento'], ['mercadopago', 'pagseguro'], true)) {
            Configuracao::set('gateway_pagamento', $_POST['gateway_pagamento']);
        }
        $this->redirect('/admin/configuracoes?salvo=1');
    }

    // ─── LOGS ────────────────────────────────────────────────────
    public function logs(): void
    {
        AuthService::requireAuth('admin');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $pdo    = getPDO();
        $offset = ($pagina - 1) * 25;

        $appLogs = $appTotal = 0;
        try {
            $stmt = $pdo->prepare("SELECT * FROM app_logs ORDER BY criado_em DESC LIMIT 25 OFFSET :o");
            $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $appLogs  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $appTotal = (int)$pdo->query("SELECT COUNT(*) FROM app_logs")->fetchColumn();
        } catch (Throwable $e) { $appLogs = []; $appTotal = 0; }

        $webhookLogs = $webhookTotal = 0;
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM logs_webhook ORDER BY criado_em DESC LIMIT 25 OFFSET :o");
            $stmt2->bindValue(':o', $offset, PDO::PARAM_INT);
            $stmt2->execute();
            $webhookLogs  = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $webhookTotal = (int)$pdo->query("SELECT COUNT(*) FROM logs_webhook")->fetchColumn();
        } catch (Throwable $e) { $webhookLogs = []; $webhookTotal = 0; }

        $logFile  = (defined('LOG_DIR') ? (string)LOG_DIR : (dirname(__DIR__, 2) . '/logs')) . '/app.log';
        $fileTail = [];
        if (is_file($logFile)) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) $fileTail = array_slice($lines, -200);
        }
        require __DIR__ . '/../Views/admin/logs.php';
    }

    // ─── CHAT (visualização pelo admin) ─────────────────────────
    public function chat(): void
    {
        AuthService::requireAuth('admin');
        $pedidoId  = (int)($_GET['pedido_id'] ?? 0);
        $mensagens = $pedidoId ? Chat::listarPorPedido($pedidoId) : [];
        $pedido    = $pedidoId ? Pedido::buscarPorId($pedidoId)   : null;
        require __DIR__ . '/../Views/admin/pedidodetalhe.php';
    }

    // ─── PEDIDO: Alterar Status, Cancelar, Atribuir ─────────────
    public function pedidoAlterarStatus(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $pedidoId = (int)($_POST["pedido_id"] ?? 0);
        $status   = $_POST["status"] ?? "";
        $validos  = ["aguardando_pagamento","aguardando_guincho","a_caminho","no_local","em_reboque","concluido","cancelado"];
        if ($pedidoId && in_array($status, $validos)) {
            Pedido::atualizarStatus($pedidoId, $status);
        }
        $this->redirect("/admin/pedido/{$pedidoId}?msg=status_atualizado");
    }

    public function pedidoCancelar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $pedidoId = (int)($_POST["pedido_id"] ?? 0);
        if ($pedidoId) {
            Pedido::atualizarStatus($pedidoId, "cancelado");
        }
        $this->redirect("/admin/pedido/{$pedidoId}?msg=cancelado");
    }

    public function pedidoAtribuir(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $pedidoId  = (int)($_POST["pedido_id"] ?? 0);
        $guinchoId = (int)($_POST["guincho_id"] ?? 0);
        if ($pedidoId && $guinchoId) {
            Pedido::atribuirGuincho($pedidoId, $guinchoId);
            Pedido::atualizarStatus($pedidoId, "a_caminho");
        }
        $this->redirect("/admin/pedido/{$pedidoId}?msg=guincho_atribuido");
    }

    // ─── USUÁRIO: Editar ──────────────────────────────────────────
    public function usuarioEditar(int $id): void
    {
        AuthService::requireAuth("admin");
        $usuario = Usuario::buscarPorId($id);
        if (!$usuario) { http_response_code(404); echo "<h1>404</h1>"; exit; }
        $extra = null;
        if ($usuario["tipo"] === "guincho") {
            $extra = Guincho::buscarPorUsuario($id);
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . "/../Views/admin/usuarioedit.php";
    }

    public function usuarioAtualizar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id    = (int)($_POST["id"] ?? 0);
        $nome  = trim($_POST["nome"]  ?? "");
        $email = trim($_POST["email"] ?? "");
        $tel   = preg_replace("/\D/", "", $_POST["telefone"] ?? "");
        $cpf   = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
        $ativo = (int)($_POST["ativo"] ?? 1);
        if (!$id || empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=1");
        }
        $pdo = getPDO();
        $dup = $pdo->prepare("SELECT id FROM usuarios WHERE (email=? OR cpf=?) AND id!=? LIMIT 1");
        $dup->execute([$email, $cpf, $id]);
        if ($dup->fetch()) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=email_duplicado");
        }
        $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=?, cpf=?, ativo=? WHERE id=?")
            ->execute([$nome, $email, $tel, $cpf, $ativo, $id]);
        $this->redirect("/admin/usuario/editar/{$id}?salvo=1");
    }

    public function usuarioSenha(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id    = (int)($_POST["id"] ?? 0);
        $senha = $_POST["senha"] ?? "";
        $conf  = $_POST["confirmar"] ?? "";
        if (!$id || strlen($senha) < 8 || $senha !== $conf) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=1");
        }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        getPDO()->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")
                ->execute([$hash, $id]);
        $this->redirect("/admin/usuario/editar/{$id}?salvo=1");
    }

    // ─── GUINCHO: Listar, Form, Criar, Atualizar ─────────────────
    public function guinchos(): void
    {
        AuthService::requireAuth("admin");
        $pendentes = Guincho::listarPendentes();
        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone, u.ativo
                 FROM guinchos g JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.aprovado = 1 ORDER BY u.nome"
            );
            $stmt->execute();
            $aprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $aprovados = []; }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . "/../Views/admin/guinchos.php";
    }

    public function guinchoNovoForm(): void
    {
        AuthService::requireAuth("admin");
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . "/../Views/admin/guinchoform.php";
    }

    public function guinhoCriar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $nome    = trim($_POST["nome"]  ?? "");
        $email   = trim($_POST["email"] ?? "");
        $tel     = preg_replace("/\D/", "", $_POST["telefone"] ?? "");
        $cpf     = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
        $senha   = $_POST["senha"] ?? "";
        $confirma = $_POST["confirmar_senha"] ?? "";
        if (empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || $senha !== $confirma) {
            $this->redirect("/admin/guincho/novo?erro=1");
        }
        $pdo = getPDO();
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email=? OR cpf=? LIMIT 1");
        $existe->execute([$email, $cpf]);
        if ($existe->fetch()) { $this->redirect("/admin/guincho/novo?erro=email_duplicado"); }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em) VALUES (?,?,?,?,?,'guincho',1,NOW())")->execute([$nome,$email,$hash,$tel,$cpf]);
        $usuarioId = (int)$pdo->lastInsertId();
        $aprovado = (int)($_POST["aprovado"] ?? 0);
        $dados = [
            "cnh_numero"       => trim($_POST["cnh_numero"] ?? ""),
            "cnh_validade"     => $_POST["cnh_validade"] ?? date("Y-m-d", strtotime("+5 years")),
            "placa_guincho"    => strtoupper(trim($_POST["placa_guincho"] ?? "")),
            "capacidade_ton"   => (float)($_POST["capacidade_ton"] ?? 0),
            "raio_cobertura_km"=> (int)($_POST["raio_cobertura_km"] ?? 20),
            "chave_pix"        => trim($_POST["chave_pix"] ?? ""),
            "chave_pix_tipo"   => $_POST["chave_pix_tipo"] ?? "cpf",
            "foto_veiculo"     => $this->processarUpload("foto_veiculo"),
            "doc_cnh_frente"   => $this->processarUpload("doc_cnh_frente"),
            "doc_cnh_verso"    => $this->processarUpload("doc_cnh_verso"),
        ];
        $guinchoId = Guincho::criarDeRegistro($usuarioId, $dados);
        if ($guinchoId && $aprovado) { Guincho::aprovar((int)$guinchoId); }
        $this->redirect("/admin/guinchos?msg=criado");
    }

    public function guinchoAtualizar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST["id"] ?? 0);
        if (!$id) { $this->redirect("/admin/guinchos"); }
        $g = Guincho::buscarPorId($id);
        if (!$g) { $this->redirect("/admin/guinchos"); }
        getPDO()->prepare(
            "UPDATE guinchos SET placa_guincho=?, cnh_numero=?, cnh_validade=?,
             chave_pix=?, chave_pix_tipo=?, capacidade_ton=?, aprovado=? WHERE id=?"
        )->execute([
            strtoupper(trim($_POST["placa_guincho"] ?? "")),
            trim($_POST["cnh_numero"] ?? ""),
            $_POST["cnh_validade"] ?? date("Y-m-d", strtotime("+5 years")),
            trim($_POST["chave_pix"] ?? ""),
            $_POST["chave_pix_tipo"] ?? "cpf",
            (float)($_POST["capacidade_ton"] ?? 0),
            (int)($_POST["aprovado"] ?? 0),
            $id,
        ]);
        $this->redirect("/admin/usuario/editar/{$g["usuario_id"]}?salvo=1");
    }

    // ─── AJAX: veículos por cliente (para pedidocriar.php) ────
    public function veiculosAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        if (!$clienteId) { echo json_encode(['veiculos' => []]); exit; }
        require_once __DIR__ . '/../Models/Veiculo.php';
        $veiculos = Veiculo::listarPorUsuario($clienteId);
        echo json_encode(['veiculos' => $veiculos]);
        exit;
    }

    // GET /admin/usuario/senha - redireciona para lista de usuários
    // A alteração de senha é feita dentro da tela de edição do usuário via POST
    public function clientesAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode(['clientes' => []]); exit; }
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT id, nome, email, telefone FROM usuarios
             WHERE tipo='cliente' AND ativo=1 AND (nome LIKE ? OR email LIKE ?)
             ORDER BY nome LIMIT 10"
        );
        $stmt->execute(["%$q%", "%$q%"]);
        echo json_encode(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function oficinasAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=utf-8');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        if (!$clienteId) { echo json_encode(['oficinas' => []]); exit; }
        $stmt = getPDO()->prepare(
            "SELECT id, nome, endereco, lat, lng FROM oficinas_favoritas WHERE usuario_id=? ORDER BY nome"
        );
        $stmt->execute([$clienteId]);
        echo json_encode(['oficinas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }


    public function usuarioSenhaGet(): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/usuarios?info=senha_via_editar');
    }

    // ─── HEALTH CHECK ────────────────────────────────────────────

    public function health(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/HealthService.php';
        $checks = HealthService::runAll();
        Logger::log(Logger::LEVEL_INFO, 'AdminController', 'health', 'health',
            'Health check executado.',
            ['ok' => count(array_filter($checks, fn($c) => $c['ok'])), 'total' => count($checks)]
        );
        require __DIR__ . '/../Views/admin/health.php';
    }

    // ─── SIMULADOR ───────────────────────────────────────────────

    public function simulador(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/SimulationRun.php';
        $runsRecentes = SimulationRun::listarRecentes(20);
        $csrfToken    = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/simulacao.php';
    }

    public function simularExecutar(): void
    {
        AuthService::requireAuth('admin');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $simEnabled = defined('SIMULATION_ENABLED') ? SIMULATION_ENABLED : (env('SIMULATION_ENABLED', 'false') === 'true');
        if (!$simEnabled) {
            Logger::log(Logger::LEVEL_WARN, 'AdminController', 'simularExecutar', 'simulacao',
                'Tentativa de executar simulador com SIMULATION_ENABLED=false.');
            $this->redirect('/admin/simulador');
        }

        require_once __DIR__ . '/../Services/SimulationService.php';

        $dryRun  = (($_POST['pix_dry_run'] ?? '1') === '1');
        $service = new SimulationService($dryRun);
        $result  = $service->run();

        $this->redirect('/admin/simulador/' . $result['run_id']);
    }

    public function simuladorResultado(string $runId): void
    {
        AuthService::requireAuth('admin');

        if (!ctype_xdigit($runId) || strlen($runId) !== 32) {
            http_response_code(404); echo '<h1>404</h1>'; exit;
        }

        require_once __DIR__ . '/../Models/SimulationRun.php';
        require_once __DIR__ . '/../Models/SimulationStep.php';

        $run   = SimulationRun::buscarPorRunId($runId);
        $steps = SimulationStep::listarPorRun($runId);

        if (!$run) { http_response_code(404); echo '<h1>404 — Simulação não encontrada</h1>'; exit; }

        require __DIR__ . '/../Views/admin/simulacao_resultado.php';
    }

    // ─── UPLOAD DE ARQUIVOS ──────────────────────────────────────
    private function processarUpload(string $campo): ?string
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) return null;
        $file = $_FILES[$campo];
        if ($file['size'] > 5 * 1024 * 1024) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) return null;
        $destDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 3) . '/uploads');
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
        $fileName = uniqid($campo . '_') . '.' . $ext;
        $destPath = $destDir . '/' . $fileName;
        if (move_uploaded_file($file['tmp_name'], $destPath)) return $fileName;
        return null;
    }
}


// Método helper: GET /admin/usuario/suspender redireciona com aviso
// (ação real é POST)
