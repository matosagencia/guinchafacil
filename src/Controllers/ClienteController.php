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
require_once __DIR__ . '/../Services/PedidoService.php';
require_once __DIR__ . '/../Models/PedidoEvidencia.php';
require_once __DIR__ . '/../Services/POR/ProofOfRoadService.php';
require_once __DIR__ . '/../Services/POR/RoutingSnapshotService.php';
require_once __DIR__ . '/../Models/ServicoCatalogo.php';
require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../Services/Triage/TriageService.php';
require_once __DIR__ . '/../Models/PedidoOrcamento.php';
require_once __DIR__ . '/../Models/PedidoDiagnostico.php';
require_once __DIR__ . '/../Services/Dispatch/OrderVehicleRequirementService.php';
require_once __DIR__ . '/../Services/Diagnostico/DiagnosticoService.php';
require_once __DIR__ . '/../Services/Conversion/ConversionService.php';

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
        require_once __DIR__ . '/../Services/ComunicadoService.php';
        $comunicados = ComunicadoService::resolveActiveForProfile('cliente', ComunicadoService::PLACEMENT_CLIENT_DASHBOARD_TOP, 3);
        $pedidoRascunho = $_SESSION['pedido_rascunho'] ?? null;
        if (!empty($pedidoRascunho['criado_em']) && strtotime($pedidoRascunho['criado_em']) < strtotime('-30 minutes')) {
            unset($_SESSION['pedido_rascunho']);
            $pedidoRascunho = null;
        }
        $csrfToken = AuthService::gerarCsrfToken();

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
        $totalOficinas = count(Oficina::listarPorUsuario($uid));
        $pedidoAndamento = null;
        foreach ($pedidosRecentes as $p) {
            if (in_array($p['status'], ['aguardando_guincho','a_caminho','no_local','em_reboque'])) {
                $pedidoAndamento = $p; break;
            }
        }
        if ($pedidoAndamento) {
            $pedidoAndamentoDetalhe = Pedido::buscarPorId((int)$pedidoAndamento['id']);
            if ($pedidoAndamentoDetalhe) {
                $pedidoAndamento = $pedidoAndamentoDetalhe;
            }
        }
        $ultimosPedidos = $pedidosRecentes;
        $servicosCatalogo = ServicoCatalogo::listarAtivos();
        // Jornada canônica: incidentes acompanham o especialista e eventual reboque
        // sem retirar o fluxo legado de pedidos da tela.
        $incidentesAtivos = [];
        try {
            $stmtInc = $pdo->prepare(
                "SELECT i.*, ae.id AS atendimento_id, ae.status AS atendimento_status,
                        e.nome_profissional AS especialista_nome
                   FROM incidentes i
              LEFT JOIN atendimentos_especialista ae ON ae.incidente_id=i.id
                    AND ae.id=(SELECT MAX(a2.id) FROM atendimentos_especialista a2 WHERE a2.incidente_id=i.id)
              LEFT JOIN especialistas e ON e.id=ae.especialista_id
                  WHERE i.cliente_id=?
                    AND i.status NOT IN ('concluido','cancelado')
               ORDER BY i.criado_em DESC LIMIT 5"
            );
            $stmtInc->execute([$uid]);
            $incidentesAtivos = $stmtInc->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // Bases ainda sem a migration não podem derrubar o dashboard legado.
            $incidentesAtivos = [];
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

        $fuelTypes = ['flex', 'gasolina', 'etanol', 'diesel', 'gnv', 'eletrico'];
        $transmissionTypes = ['manual', 'automatico'];
        $electricTypes = ['nao_eletrico', 'hibrido', 'eletrico_puro'];

        // vehicle_type/operational_category derivam do radio "tipo" já existente
        // (carro/moto/caminhao/van) — evita perguntar o tipo duas vezes no
        // mesmo formulário.
        $tipoParaVehicleType = [
            'carro' => 'automovel_passeio',
            'moto' => 'moto',
            'caminhao' => 'caminhao_leve',
            'van' => 'utilitario',
        ];
        $tipoPost = $_POST['tipo'] ?? 'carro';
        $vehicleType = $tipoParaVehicleType[$tipoPost] ?? 'automovel_passeio';

        $dados = [
            'placa'  => strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['placa'] ?? ''))),
            'cidade_placa' => trim((string)($_POST['cidade_placa'] ?? '')),
            'uf_placa' => strtoupper(trim((string)($_POST['uf_placa'] ?? ''))),
            'marca'  => trim($_POST['marca']  ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            // §CATALOGO-VISUAL-01: preenchidos pelo autocomplete de marca/
            // modelo quando o texto digitado casa com o catálogo — ficam
            // vazios/null se o cliente digitou algo fora do catálogo (o
            // cadastro continua funcionando por texto livre nesse caso).
            'vehicle_brand_id' => !empty($_POST['vehicle_brand_id']) ? (int)$_POST['vehicle_brand_id'] : null,
            'vehicle_model_id' => !empty($_POST['vehicle_model_id']) ? (int)$_POST['vehicle_model_id'] : null,
            'ano'    => (int)($_POST['ano']   ?? 0),
            'cor'    => trim($_POST['cor']    ?? ''),
            'tipo'   => in_array($_POST['tipo'] ?? '', ['carro','moto','caminhao','van']) ? $_POST['tipo'] : 'carro',
            'vehicle_type' => $vehicleType,
            'fuel_type' => in_array($_POST['fuel_type'] ?? '', $fuelTypes, true) ? $_POST['fuel_type'] : null,
            'transmission_type' => in_array($_POST['transmission_type'] ?? '', $transmissionTypes, true) ? $_POST['transmission_type'] : null,
            'electric_type' => in_array($_POST['electric_type'] ?? '', $electricTypes, true) ? $_POST['electric_type'] : null,
            'operational_category' => $vehicleType,
            'has_spare_tire' => isset($_POST['has_spare_tire']) ? (int)!!$_POST['has_spare_tire'] : null,
            'has_locking_bolt' => isset($_POST['has_locking_bolt']) ? (int)!!$_POST['has_locking_bolt'] : null,
        ];
        if (empty($dados['marca']) || empty($dados['modelo']) || $dados['ano'] < 1950) {
            $this->redirect('/cliente/veiculo/novo?erro=1');
        }
        if (!empty($dados['uf_placa']) && !preg_match('/^[A-Z]{2}$/', $dados['uf_placa'])) {
            $this->redirect('/cliente/veiculo/novo?erro=1');
        }

        // Documento (CRLV-e) é opcional — falha no upload não bloqueia o
        // cadastro, só é ignorada silenciosamente (o cliente pode enviar
        // depois, editando o veículo).
        if (!empty($_FILES['documento']['name']) && ($_FILES['documento']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $path = $this->armazenarDocumentoVeiculo($_FILES['documento']);
            if ($path !== null) {
                $dados['document_uploaded'] = 1;
                $dados['document_path'] = $path;
            }
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

    /**
     * Salva o documento opcional (CRLV-e) fora do webroot, mesmo padrão
     * de storage privado usado em EvidenceService::privateStorageDir().
     * Sem viewer autenticado ainda (fica para quando o admin precisar
     * conferir — hoje só eleva verification_status para DOCUMENT_SUBMITTED).
     */
    private function armazenarDocumentoVeiculo(array $file): ?string
    {
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) {
            return null;
        }
        $destDir = rtrim(dirname((string)PUBLIC_PATH), '/\\') . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'veiculos_documentos';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0770, true);
        }
        $storedName = sprintf('veic_%d_%s.%s', $this->usuarioId(), bin2hex(random_bytes(8)), $allowed[$mime]);
        $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;
        $moved = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $destPath)
            : rename((string)$file['tmp_name'], $destPath);
        return $moved ? $storedName : null;
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

    public function perfilForm(): void
    {
        AuthService::requireAuth('cliente');
        $uid = $this->usuarioId();
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $csrfToken = AuthService::gerarCsrfToken();
        $flashMsg  = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        require __DIR__ . '/../Views/cliente/perfil.php';
    }

    public function perfilSalvar(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $uid = $this->usuarioId();
        $pdo = getPDO();

        try {
            $pdo->beginTransaction();

            $nome = trim((string)($_POST['nome'] ?? ''));
            $telefone = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));

            if (mb_strlen($nome) < 3) {
                throw new Exception('Nome deve ter ao menos 3 caracteres.');
            }
            if (strlen($telefone) < 10) {
                throw new Exception('Telefone inválido.');
            }

            if (!empty($_POST['nova_senha'])) {
                $novaSenha = (string)$_POST['nova_senha'];
                $confirmar = (string)($_POST['confirmar_senha'] ?? '');
                if (strlen($novaSenha) < 8) {
                    throw new Exception('Nova senha deve ter ao menos 8 caracteres.');
                }
                if ($novaSenha !== $confirmar) {
                    throw new Exception('As senhas não conferem.');
                }

                $stmtSenha = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1");
                $stmtSenha->execute([$uid]);
                $row = $stmtSenha->fetch(PDO::FETCH_ASSOC);
                if (!password_verify((string)($_POST['senha_atual'] ?? ''), (string)($row['senha_hash'] ?? ''))) {
                    throw new Exception('Senha atual incorreta.');
                }

                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ?, senha_hash = ? WHERE id = ?")
                    ->execute([$nome, $telefone, password_hash($novaSenha, PASSWORD_BCRYPT), $uid]);
            } else {
                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ? WHERE id = ?")
                    ->execute([$nome, $telefone, $uid]);
            }

            if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                $_SESSION['user']['nome'] = $nome;
                $_SESSION['user']['telefone'] = $telefone;
            }

            $pdo->commit();
            $_SESSION['_flash'][] = ['message' => 'Perfil atualizado com sucesso!', 'type' => 'success'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error'];
        }

        $this->redirect('/cliente/perfil');
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

    public function financeiro(): void
    {
        AuthService::requireAuth('cliente');
        $uid = $this->usuarioId();
        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));

        $pagamentos = Pagamento::extratoCliente($uid, $mes, $ano);
        $totais = Pagamento::totaisExtratoCliente($uid, $mes, $ano);

        require __DIR__ . '/../Views/cliente/financeiro.php';
    }

    /**
     * ROADMAP socorro automotivo — Fundamento 3 (triagem). GET mostra a
     * pergunta 1 + perguntas 2 dinâmicas (JS mostra/esconde por sintoma,
     * ver triagem.php); POST vai para triagemResponder().
     */
    public function triagem(): void
    {
        AuthService::requireAuth('cliente');
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/triagem.php';
    }

    public function triagemResponder(): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $symptomCode = strtoupper(trim((string)($_POST['symptom_code'] ?? '')));
        $respostas = [];
        foreach ((array)($_POST['resposta'] ?? []) as $chave => $valor) {
            $chaveSegura = preg_replace('/[^a-z_]/', '', strtolower((string)$chave));
            if ($chaveSegura === '') {
                continue;
            }
            $respostas[$chaveSegura] = is_scalar($valor) ? (string)$valor : '';
        }

        $sessionToken = (string)($_POST['session_token'] ?? bin2hex(random_bytes(16)));
        $request = new TriageRequest($symptomCode, $respostas);
        $service = new TriageService();
        $sessao = $service->avaliarEPersistir($sessionToken, $request, $this->usuarioId());

        $this->redirect('/cliente/triagem/resultado?token=' . urlencode($sessao['session_token']));
    }

    public function triagemResultado(): void
    {
        AuthService::requireAuth('cliente');
        $token = (string)($_GET['token'] ?? '');
        $service = new TriageService();
        $sessao = $token !== '' ? $service->buscarPorToken($token) : null;

        if (!$sessao) {
            $this->redirect('/cliente/triagem');
        }

        $servicoRecomendado = $service->resolverServiceTypeRecomendado($sessao);
        $alternativas = [];
        foreach ($sessao['alternative_service_codes'] as $code) {
            $t = ServiceType::buscarPorCodigo((string)$code);
            if ($t && !empty($t['active'])) {
                $alternativas[] = $t;
            }
        }

        require __DIR__ . '/../Views/cliente/triagem_resultado.php';
    }

    /**
     * ROADMAP socorro automotivo — UX (22/07): endpoint JSON usado pelo
     * wizard embutido em /cliente/pedido/novo, para avaliar a triagem SEM
     * navegar para outra página (padrão "mapa + busca no topo + bottom
     * sheet" tipo Uber — a triagem acontece no mesmo fluxo do pedido).
     * Reaproveita o mesmíssimo TriageService/TriageRuleEngine da tela
     * dedicada /cliente/triagem — mesma fonte de verdade, dois pontos de
     * entrada de UI.
     */
    public function triagemAvaliarJson(): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json; charset=UTF-8');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $symptomCode = strtoupper(trim((string)($_POST['symptom_code'] ?? '')));
        $respostas = [];
        foreach ((array)($_POST['resposta'] ?? []) as $chave => $valor) {
            $chaveSegura = preg_replace('/[^a-z_]/', '', strtolower((string)$chave));
            if ($chaveSegura === '') {
                continue;
            }
            $respostas[$chaveSegura] = is_scalar($valor) ? (string)$valor : '';
        }

        $sessionToken = (string)($_POST['session_token'] ?? bin2hex(random_bytes(16)));
        $request = new TriageRequest($symptomCode, $respostas);
        $service = new TriageService();
        $sessao = $service->avaliarEPersistir($sessionToken, $request, $this->usuarioId());

        $recomendado = $service->resolverServiceTypeRecomendado($sessao);
        $alternativas = [];
        foreach ($sessao['alternative_service_codes'] as $code) {
            $t = ServiceType::buscarPorCodigo((string)$code);
            if ($t && !empty($t['active'])) {
                $alternativas[] = ['id' => (int)$t['id'], 'code' => $t['code'], 'name' => $t['name'], 'requires_destination' => (bool)$t['requires_destination']];
            }
        }

        echo json_encode([
            'ok' => true,
            'session_token' => $sessao['session_token'],
            'resultado' => $sessao['resultado'],
            'safety_risk' => (bool)$sessao['safety_risk'],
            'explicacao' => $sessao['explicacao'],
            'recomendado' => $recomendado ? [
                'id' => (int)$recomendado['id'],
                'code' => $recomendado['code'],
                'name' => $recomendado['name'],
                'requires_destination' => (bool)$recomendado['requires_destination'],
            ] : null,
            'alternativas' => $alternativas,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function pedidoNovo(): void
    {
        AuthService::requireAuth('cliente');
        $uid     = $this->usuarioId();
        $veiculos = Veiculo::listarPorUsuario($uid);
        $oficinas = Oficina::listarPorUsuario($uid);
        $cfg      = Configuracao::getAll();
        $pedidoRascunho = $_SESSION['pedido_rascunho'] ?? null;
        if (!empty($pedidoRascunho['criado_em']) && strtotime($pedidoRascunho['criado_em']) < strtotime('-30 minutes')) {
            unset($_SESSION['pedido_rascunho']);
            $pedidoRascunho = null;
        }

        // ROADMAP socorro automotivo — Etapa 2: recomendação vinda da triagem
        // (/cliente/triagem) chega aqui como service_type_id na querystring.
        // Sempre revalidada contra o catálogo ativo — nunca confiar no valor cru.
        $triagemServiceType = null;
        $tsId = (int)($_GET['service_type_id'] ?? 0);
        if ($tsId > 0) {
            $candidato = ServiceType::buscarPorId($tsId);
            if ($candidato && !empty($candidato['active'])) {
                $triagemServiceType = $candidato;
            }
        }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/pedidonovo.php';
    }

    public function pedidoRascunho(): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json; charset=UTF-8');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $endereco = trim((string)($_POST['endereco_origem'] ?? ''));
        $lat = isset($_POST['lat_origem']) ? (float)$_POST['lat_origem'] : 0.0;
        $lng = isset($_POST['lng_origem']) ? (float)$_POST['lng_origem'] : 0.0;
        // Pacote L1.8 — contrato do plano: 'source' identifica se a origem veio do
        // GPS do dispositivo ou de digitação/autocomplete; hoje é só telemetria/log,
        // não muda a validação.
        $source = (string)($_POST['source'] ?? 'autocomplete');
        if (!in_array($source, ['gps', 'autocomplete'], true)) {
            $source = 'autocomplete';
        }

        if ($endereco === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Informe a localização do veículo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($lat !== 0.0 && ($lat < -34 || $lat > 5 || $lng < -74 || $lng > -28)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas fora do limite aceito.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // draft_id: identifica este rascunho especificamente, para a tela de pedido
        // novo confirmar que está usando os dados corretos (e não um rascunho velho
        // sobrado de sessão) — parte do contrato explícito do plano (seção 4.9).
        $draftId = bin2hex(random_bytes(12));

        $_SESSION['pedido_rascunho'] = [
            'draft_id' => $draftId,
            'endereco_origem' => mb_substr($endereco, 0, 220),
            'lat_origem' => $lat ?: null,
            'lng_origem' => $lng ?: null,
            'source' => $source,
            'criado_em' => date('Y-m-d H:i:s'),
        ];

        echo json_encode(['ok' => true, 'draft_id' => $draftId, 'redirect' => '/cliente/pedido/novo'], JSON_UNESCAPED_UNICODE);
        exit;
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

        // ROADMAP socorro automotivo — Etapa 2: service_type_id vindo da triagem
        // (input hidden em pedidonovo.php), sempre revalidado contra o catálogo
        // ativo. Coluna aditiva/opcional (Etapa 1) — ausência não muda nada.
        $serviceTypeId = null;
        // Default 'TOWING' preserva o comportamento de sempre para pedidos
        // sem triagem (coluna já nasce assim por DEFAULT no schema) — só
        // sobrescrevemos quando um tipo de serviço válido foi de fato
        // escolhido. Isso é o que faz Etapa 4 (matching por capacidade)
        // funcionar corretamente: sem isso, todo pedido ficava marcado como
        // TOWING mesmo sendo partida auxiliar/pneu/chaveiro etc.
        $attendanceMode = 'TOWING';
        $serviceTypeIdPost = (int)($_POST['service_type_id'] ?? 0);
        if ($serviceTypeIdPost > 0) {
            $tipoServico = ServiceType::buscarPorId($serviceTypeIdPost);
            if ($tipoServico && !empty($tipoServico['active'])) {
                $serviceTypeId = (int)$tipoServico['id'];
                $attendanceMode = (string)($tipoServico['attendance_mode'] ?? 'TOWING');
            }
        }

        // §DESLOCAMENTO-01 (26/07/2026, correção de lacuna apontada pelo
        // usuário): antes desta correção, TODO pedido — inclusive serviços
        // ON_SITE (partida auxiliar, diagnóstico elétrico, pneu etc.) — era
        // custeado com a tarifa de REBOQUE (TarifaService::calcular),
        // ignorando completamente base_fee/pickup_km_price/labor_fee
        // configurados em /admin/catalogo-servicos/tarifas para o tipo de
        // serviço real escolhido. Agora: 1) tenta a zona de precificação
        // (ZonePricingService, Etapa 13) primeiro; 2) se o serviço NÃO for
        // de reboque, usa a tarifa própria do serviço
        // (ServicePricingRule::calcularTotal, com deslocamento real
        // incluído); 3) só cai no cálculo de reboque quando for
        // efetivamente TOWING ou quando o serviço não tiver tarifa própria
        // configurada (rede de segurança — nunca trava a criação do pedido).
        require_once __DIR__ . '/../Services/TarifaService.php';
        require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';
        require_once __DIR__ . '/../Models/Cidade.php';
        $categoriaVeiculo = TarifaService::categoriaDeVeiculo($veiculo);

        // §PRECO-POR-CIDADE-01: resolve a cidade-alvo do pedido pela
        // coordenada de origem (null quando nenhuma cidade tem geo
        // configurada — comportamento idêntico ao de antes desta
        // funcionalidade). Isso alimenta o FALLBACK global (TarifaService/
        // ServicePricingRule) com um preço específico da cidade quando
        // existir; a camada de zona (ZonePricingService) já era
        // inerentemente por-geografia e continua tendo prioridade.
        $cidadeResolvida = Cidade::resolverPorCoordenada($latOrigem, $lngOrigem);
        $cidadeIdPreco = $cidadeResolvida['id'] ?? null;

        // Regras de zona usam o TIPO cru do veículo (carro/moto/caminhao/...)
        // como vehicle_category, não a categoria tarifária normalizada do
        // TarifaService (que funde carro+moto em "popular") — é o que
        // permite diferenciar preço de pane elétrica/reboque de moto vs
        // carro, coisa que o TarifaService nunca fez.
        $custo = null;
        // Especialistas usam o catálogo comercial próprio (atendimento no
        // local + adicional tabelado). Tarifas de zona/reboque não podem
        // transformar uma partida simples em R$149 automaticamente.
        if ($attendanceMode !== 'TOWING' && $serviceTypeId) {
            require_once __DIR__ . '/../Services/EspecialistaPricingService.php';
            $codigoEspecialista = (string)($tipoServico['code'] ?? '');
            $precoEspecialista = EspecialistaPricingService::calcular($codigoEspecialista, $distancia);
            if ($precoEspecialista !== null) {
                $custo = (float)$precoEspecialista['customer_amount'];
            }
        }
        $zonaPreco = ZonePricingService::calcularPreco(
            $latOrigem, $lngOrigem,
            $serviceTypeId ?: 0,
            (string)($veiculo['tipo'] ?? ''),
            $distancia
        );
        if ($custo === null && $zonaPreco !== null) {
            $custo = $zonaPreco['valor'];
        } elseif ($custo === null && $attendanceMode !== 'TOWING' && $serviceTypeId) {
            $porServico = ServicePricingRule::calcularTotal($serviceTypeId, $distancia, null, $cidadeIdPreco);
            if ($porServico !== null) {
                $custo = $porServico['valor'];
            }
        }
        if ($custo === null) {
            $custo = TarifaService::calcular($distancia, $categoriaVeiculo, false, null, $cidadeIdPreco);
        }

        // §CELULAS-NITEROI-01 (04/08/2026): marca em qual célula territorial
        // (pricing_zones) o pedido caiu, independente de existir regra de
        // preço pra essa zona — é só uma tag analítica pro painel de
        // gates/marcos por célula, não interfere no valor calculado acima.
        // Fica null enquanto o admin não desenhar o polígono da célula.
        $pricingZoneId = null;
        $zonaResolvida = ZonePricingService::resolverZonaPorCoordenada($latOrigem, $lngOrigem);
        if ($zonaResolvida !== null) {
            $pricingZoneId = (int)$zonaResolvida['id'];
        }

        // §7: coordenadas obrigatórias e dentro dos limites do Brasil (lat [-34,5], lng [-74,-28])
        $latValida = static fn(float $v): bool => $v >= -34 && $v <= 5;
        $lngValida = static fn(float $v): bool => $v >= -74 && $v <= -28;

        if ($latOrigem === 0.0 || $lngOrigem === 0.0 || !$latValida($latOrigem) || !$lngValida($lngOrigem)) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_origem');
        }
        if ($latDest === 0.0 || $lngDest === 0.0 || !$latValida($latDest) || !$lngValida($lngDest)) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_destino');
        }

        // §COBERTURA-RAIO-01 (05/08/2026): não deixa nem abrir o pedido se
        // nenhum guincho aprovado (com capacidade pro tipo de serviço,
        // quando aplicável) alcança essa coordenada dentro do próprio raio
        // efetivo dele — mesma regra usada na fila real de ofertas
        // (GuinchoController::montarOfertasDisponiveis / SseController), pra
        // nunca prometer cobertura que o guincheiro não vai ver. Não olha
        // status_expansao da célula (pedra_viva/pedra_morta são agnósticos
        // aqui) nem exige guincho "disponivel" agora — só que exista alguém
        // estruturalmente acionável; o cron de expiração em 30 min
        // (cron_cancelar_pedidos_expirados.php + EstornoService) cobre o
        // caso de ninguém aceitar a tempo.
        require_once __DIR__ . '/../Services/CoberturaService.php';
        $diagnosticoCobertura = CoberturaService::diagnosticarAtendimento([
            'attendance_mode' => $attendanceMode,
            'lat_origem' => $latOrigem,
            'lng_origem' => $lngOrigem,
            'service_type_id' => $serviceTypeId,
        ]);
        if (($diagnosticoCobertura['pode_cobrar'] ?? true) !== true) {
            $erroCobertura = (string)($diagnosticoCobertura['status'] ?? 'sem_cobertura');
            if (!in_array($erroCobertura, ['sem_cobertura', 'somente_reboque'], true)) {
                $erroCobertura = 'sem_cobertura';
            }
            $this->redirect('/cliente/pedido/novo?erro=' . $erroCobertura);
        }

        if (empty($endOrigem)) $endOrigem = 'Localização atual';
        if (empty($endDest))   $endDest   = 'Destino informado';

        // Etapa 14 — perguntas situacionais repetidas a cada pedido (o estado
        // do veículo muda; não dá pra confiar só no cadastro). Nullable:
        // ausência de resposta não bloqueia o pedido (checkbox desmarcado no
        // form não envia nada, então tratamos "não veio" como null, não como
        // "não").
        $veiculoBatido = isset($_POST['veiculo_esta_batido']) ? (int)!!$_POST['veiculo_esta_batido'] : null;
        $rodasTravadas = isset($_POST['rodas_travadas']) ? (int)!!$_POST['rodas_travadas'] : null;
        $dificilAcesso = isset($_POST['local_dificil_acesso']) ? (int)!!$_POST['local_dificil_acesso'] : null;
        $garagemSubsolo = isset($_POST['em_garagem_subsolo']) ? (int)!!$_POST['em_garagem_subsolo'] : null;

        $pService = new PedidoService();
        $statusInicial = $pService->statusInicialPedido();
        $pdo = getPDO();
        require_once __DIR__ . '/../Services/MarketingAttributionService.php';
        $atribuicao = MarketingAttributionService::forPedido();
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (cliente_id, veiculo_id, tipo_problema, descricao_problema,
             lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
             distancia_km, custo_estimado, status, raio_atual_km, score_minimo_atual,
             expiracao_aceite, criado_em, service_type_id, attendance_mode,
             veiculo_esta_batido, rodas_travadas, local_dificil_acesso, em_garagem_subsolo,
             utm_source, utm_medium, utm_campaign, utm_content, utm_term, canal_aquisicao, referrer_url, landing_page, cidade_id, pricing_zone_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,10,0.5000,
             DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )"
        );
        $stmt->execute([
            $uid, $veiculoId, $tipo, $descricao,
            $latOrigem, $lngOrigem, $endOrigem,
            $latDest,   $lngDest,   $endDest,
            $distancia, $custo, $statusInicial,
            $serviceTypeId, $attendanceMode,
            $veiculoBatido, $rodasTravadas, $dificilAcesso, $garagemSubsolo,
            $atribuicao['utm_source'] ?? null, $atribuicao['utm_medium'] ?? null, $atribuicao['utm_campaign'] ?? null,
            $atribuicao['utm_content'] ?? null, $atribuicao['utm_term'] ?? null, $atribuicao['canal_aquisicao'] ?? 'organico',
            $atribuicao['referrer_url'] ?? null, $atribuicao['landing_page'] ?? null,
            $cidadeIdPreco, $pricingZoneId,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();

        // Etapa 15 — congela o cenário veicular/situacional deste pedido. É
        // este snapshot que a compatibilidade lê no aceite, não o cadastro
        // atual do veículo (que pode mudar de estado depois). Falha aqui não
        // bloqueia o pedido — o serviço já existiu antes da Etapa 15.
        try {
            OrderVehicleRequirementService::registrar($pedidoId, $veiculo, [
                'batido' => $veiculoBatido,
                'rodas_travadas' => $rodasTravadas,
                'dificil_acesso' => $dificilAcesso,
                'garagem_subsolo' => $garagemSubsolo,
            ]);
        } catch (\Throwable $e) {
            error_log('[Etapa15] snapshot veicular falhou p/ pedido ' . $pedidoId . ': ' . $e->getMessage());
        }

        if ($pService->podeIniciarAtendimento()) {
            $expMin = (int)($cfg['tempo_expiracao_min'] ?? 5);
            $raioInicial = (int)($cfg['raio_inicial_km'] ?? 10);
            Pedido::definirExpiracao($pedidoId, date('Y-m-d H:i:s', strtotime("+{$expMin} minutes")), $raioInicial);
            unset($_SESSION['pedido_rascunho']);

            error_log(sprintf(
                '[Payment] Flow Mode = %s | Pedido %d liberado sem gateway.',
                strtoupper($pService->modoOperacao()),
                $pedidoId
            ));

            $this->redirect("/cliente/dashboard?msg=pedido_criado");
        } else {
            $this->redirect("/pagamento/checkout/{$pedidoId}");
        }
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
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token inválido.', 'taxa' => 0, 'estorno' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $uid = $this->usuarioId();
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        if ($motivo === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Informe o motivo do cancelamento.', 'taxa' => 0, 'estorno' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Pacote L1.6: confirmação exige snapshot_id obtido em /cliente/pedido/{id}/cancelamento-preview.
        $snapshotIdRaw = $_POST['snapshot_id'] ?? null;
        $snapshotId = ($snapshotIdRaw !== null && $snapshotIdRaw !== '') ? (int)$snapshotIdRaw : null;
        if ($snapshotId === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Solicite o preview de cancelamento antes de confirmar.', 'taxa' => 0, 'estorno' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Services/CancelamentoService.php';
        try {
            $resultado = CancelamentoService::cancelarPorCliente($id, $uid, $motivo, $snapshotId);
            http_response_code($resultado['ok'] ? 200 : 409);
            echo json_encode([
                'ok' => (bool)$resultado['ok'],
                'erro' => $resultado['erro'],
                'taxa' => (float)($resultado['taxa'] ?? 0),
                'estorno' => (bool)($resultado['estorno']['sucesso'] ?? false),
                'snapshot_id' => $resultado['snapshot_id'] ?? $snapshotId,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'cancelamento_cliente', $e, [
                'pedido_id' => $id,
                'cliente_id' => $uid,
                'fase' => 'cancelamento_constitucional',
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Erro interno ao cancelar.', 'taxa' => 0, 'estorno' => false], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * GET /cliente/pedido/{id}/cancelamento-preview
     * Pacote L1.6 — calcula E persiste o snapshot de cancelamento (versão da
     * fórmula, fatores, taxa, estorno previsto) que a tela exibe no modal.
     * O snapshot_id devolvido aqui é obrigatório para confirmar o cancelamento.
     */
    public function cancelamentoPreview(int $id): void
    {
        AuthService::requireAuth('cliente', false);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $uid = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Services/CancelamentoService.php';
        try {
            $preview = CancelamentoService::previewClienteComSnapshot($pedido, $uid);
            echo json_encode(['ok' => true] + $preview, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'cancelamento_preview', $e, [
                'pedido_id' => $id, 'cliente_id' => $uid,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Erro interno ao calcular preview.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
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
        require_once __DIR__ . '/../Services/CancelamentoService.php';
        $cancelPreview = CancelamentoService::previewCliente($pedido);
        $routingSnapshot = RoutingSnapshotService::buildForPedido($pedido);
        $pedido['especialista_atendimentos'] = [];
        if (!empty($pedido['incidente_id'])) {
            $ev = getPDO()->prepare("SELECT ev.id, ev.evento, ev.criado_em, ev.metadata_json, e.nome_profissional, s.nome AS servico_nome FROM atendimento_eventos ev JOIN atendimentos_especialista a ON a.id=ev.atendimento_id LEFT JOIN especialistas e ON e.id=a.especialista_id LEFT JOIN servicos_especialista s ON s.id=a.servico_solicitado_id WHERE ev.incidente_id=? ORDER BY ev.criado_em ASC");
            $ev->execute([(int)$pedido['incidente_id']]); $pedido['especialista_atendimentos'] = $ev->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Após o aceite, o cliente recebe a identificação operacional do
        // prestador: nome, telefone, especialidades aprovadas e veículo.
        $pedido['guincho_especialidades'] = [];
        if (!empty($pedido['guincho_id'])) {
            require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
            try {
                $pedido['guincho_especialidades'] = ProviderCapability::listarAprovadasPorPrestador((int)$pedido['guincho_id']);
            } catch (Throwable) {
                $pedido['guincho_especialidades'] = [];
            }
        }

        $flash = $this->getFlashMessage();

        // Etapa 5 — orçamento complementar pendente de decisão do cliente.
        $orcamentoPendente = null;
        $conversaoPendente = false;
        $diagnosticoAtual = null;
        if ((string)($pedido['attendance_mode'] ?? 'TOWING') !== 'TOWING') {
            $orc = PedidoOrcamento::buscarPorPedido($id);
            if ($orc && $orc['status'] === PedidoOrcamento::PENDENTE) {
                $orcamentoPendente = $orc;
            }
            $conversaoPendente = (string)$pedido['status'] === 'conversao_reboque_pendente';
            $diagnosticoAtual = $conversaoPendente ? PedidoDiagnostico::buscarPorPedido($id) : null;
        }

        require __DIR__ . '/../Views/cliente/pedidostatus.php';
    }


    /**
     * Etapa 5 — cliente aprova ou recusa o orçamento complementar proposto
     * pelo prestador após o diagnóstico. Só o dono do pedido decide.
     */
    public function orcamentoDecidir(int $id): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect("/cliente/pedido/{$id}");
        }
        $uid = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            $this->redirect('/cliente/historico');
        }

        $aprovado = ($_POST['decisao'] ?? '') === 'aprovar';
        $result = DiagnosticoService::decidirOrcamento($id, $uid, $aprovado);
        if (!$result->ok) {
            $this->setFlashMessage((string)$result->error, 'error');
        } else {
            $this->setFlashMessage($aprovado ? 'Orçamento aprovado — o prestador foi liberado para executar o serviço.' : 'Orçamento recusado.', $aprovado ? 'success' : 'info');
        }
        if ($result->ok && $aprovado) {
            require_once __DIR__ . '/../Models/Financial/OrderChargeItem.php';
            require_once __DIR__ . '/../Services/Financial/SupplementalChargeService.php';
            $charges = OrderChargeItem::listarPorPedido($id);
            $charge = array_values(array_filter($charges, static fn(array $c): bool => ($c['charge_status'] ?? '') === 'AWAITING_CUSTOMER_APPROVAL'))[0] ?? null;
            if ($charge) {
                SupplementalChargeService::criarCheckout($id, (int)$charge['id'], 'mercadopago');
                $this->redirect('/pagamento/complementar/' . (int)$charge['id']);
            }
        }
        $this->redirect("/cliente/pedido/{$id}");
    }

    /**
     * Etapa 7 — cliente aprova ou recusa a conversão de socorro local para
     * reboque, proposta pelo prestador após o diagnóstico.
     */
    public function conversaoDecidir(int $id): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect("/cliente/pedido/{$id}");
        }
        $uid = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            $this->redirect('/cliente/historico');
        }

        $aprovado = ($_POST['decisao'] ?? '') === 'aprovar';
        $destino = [
            'endereco' => trim((string)($_POST['destino_endereco'] ?? '')),
            'lat' => isset($_POST['destino_lat']) && $_POST['destino_lat'] !== '' ? (float)$_POST['destino_lat'] : null,
            'lng' => isset($_POST['destino_lng']) && $_POST['destino_lng'] !== '' ? (float)$_POST['destino_lng'] : null,
        ];
        $result = ConversionService::decidirConversao($id, $uid, $aprovado, $destino);
        if (!$result->ok) {
            $this->setFlashMessage((string)$result->error, 'error');
        } else {
            $aguardandoPagamento = !empty($result->context['aguardando_pagamento_complementar']);
            if ($aguardandoPagamento) {
                $this->setFlashMessage('Conversão aprovada — falta pagar o complementar do reboque para acionarmos um guincho.', 'success');
                $this->redirect("/pagamento/checkout/{$id}");
            }
            $this->setFlashMessage(
                $aprovado ? 'Conversão para reboque aprovada — buscando um guincho disponível.' : 'Conversão recusada.',
                $aprovado ? 'success' : 'info'
            );
        }
        $this->redirect("/cliente/pedido/{$id}");
    }

    public function pedidoStatusAjax(int $id): void
    {
        AuthService::requireAuth('cliente', false);
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
            'lat_guincho'     => $pedido['lat_guincho']      ?? null,
            'lng_guincho'     => $pedido['lng_guincho']      ?? null,
        ]);
        exit;
    }

    public function pedidoStatusJson(int $id): void
    {
        AuthService::requireAuth('cliente', false);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $uid = $this->usuarioId();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'acesso_negado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Services/CancelamentoService.php';
        $cancel = CancelamentoService::previewCliente($pedido);

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'status_label' => $this->statusLabel((string)$pedido['status']),
            'tem_chat_novo' => Chat::contarNaoLidas($id, $uid) > 0,
            'guincho_nome' => $pedido['guincho_operador'] ?? null,
            'guincho_tipo' => $pedido['guincho_tipo'] ?? null,
            'guincho_foto' => $pedido['guincho_foto'] ?? null,
            'guincho_placa' => $pedido['guincho_placa'] ?? null,
            'guincho_uf' => $pedido['guincho_uf'] ?? ($pedido['uf_placa'] ?? null),
            'lat_guincho' => isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : null,
            'lng_guincho' => isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : null,
            'foto_plataforma' => $pedido['foto_plataforma'] ?? null,
            'foto_destino' => $pedido['foto_destino'] ?? null,
            'cancelado_por' => $pedido['cancelado_por'] ?? null,
            'taxa_cancelamento' => (float)($pedido['taxa_cancelamento'] ?? 0),
            'cancel_pode' => (bool)$cancel['pode'],
            'cancel_taxa' => (float)$cancel['taxa'],
            'cancel_bloqueio' => $cancel['motivo_bloqueio'],
            'cancel_isento_ate' => $cancel['isento_ate'],
            'cancel_proof_distance_m' => (float)($cancel['proof_distance_m'] ?? 0),
            'cancel_proof_duration_s' => (int)($cancel['proof_duration_s'] ?? 0),
            'tracking_quality' => $cancel['tracking_quality'] ?? 'unknown',
            'por_summary' => ProofOfRoadService::getSummary($id),
            'por_snapshot' => ProofOfRoadService::getCurrentSnapshot($id),
            'evidencias' => PedidoEvidencia::listarPorPedido($id),
            'routing' => RoutingSnapshotService::buildForPedido($pedido),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }


    private function statusLabel(string $status): string
    {
        $labels = [
            'aguardando_guincho' => 'Aguardando guincho',
            'a_caminho' => 'Guincho a caminho',
            'no_local' => 'Guincho no local',
            'em_reboque' => 'Em reboque',
            'concluido' => 'Concluido',
            'cancelado' => 'Cancelado',
        ];
        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function calcularCusto(): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json; charset=UTF-8');
        $distancia = (float)($_GET['distancia_km'] ?? 0);
        if ($distancia <= 0) {
            echo json_encode(['ok' => false, 'erro' => 'Distância inválida'], JSON_UNESCAPED_UNICODE); exit;
        }

        $categoria = trim((string)($_GET['categoria'] ?? ''));
        $tipoVeiculoCru = '';
        $veiculoId = (int)($_GET['veiculo_id'] ?? 0);
        if ($veiculoId > 0) {
            $veiculo = Veiculo::buscarPorId($veiculoId);
            if (!$veiculo || (int)$veiculo['usuario_id'] !== $this->usuarioId()) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'erro' => 'Veículo inválido'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $tipoVeiculoCru = (string)($veiculo['tipo'] ?? '');
            $categoria = $categoria !== '' ? $categoria : TarifaService::categoriaDeVeiculo($veiculo);
        }

        $prioridade = (($_GET['prioridade'] ?? '0') === '1');
        require_once __DIR__ . '/../Services/TarifaService.php';

        // §DESLOCAMENTO-01: mesma ordem de resolução usada em pedidoCriar()
        // — zona de precificação primeiro, depois tarifa própria do
        // serviço (se não for reboque), só então o cálculo de reboque como
        // rede de segurança. Sem isso, a estimativa mostrada ao cliente
        // ANTES de confirmar nunca batia com o que um serviço ON_SITE
        // realmente custava.
        $serviceTypeId = (int)($_GET['service_type_id'] ?? 0);
        $latOrigem = isset($_GET['lat_origem']) ? (float)$_GET['lat_origem'] : null;
        $lngOrigem = isset($_GET['lng_origem']) ? (float)$_GET['lng_origem'] : null;
        $attendanceMode = 'TOWING';
        if ($serviceTypeId > 0) {
            require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
            $tipoServico = ServiceType::buscarPorId($serviceTypeId);
            if ($tipoServico && !empty($tipoServico['active'])) {
                $attendanceMode = (string)($tipoServico['attendance_mode'] ?? 'TOWING');
            } else {
                $serviceTypeId = 0;
            }
        }

        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadeIdPreco = null;
        if ($latOrigem !== null && $lngOrigem !== null) {
            $cidadeResolvida = Cidade::resolverPorCoordenada($latOrigem, $lngOrigem);
            $cidadeIdPreco = $cidadeResolvida['id'] ?? null;
        }

        $origem = 'reboque';
        $detalhe = null;
        if ($attendanceMode !== 'TOWING' && $serviceTypeId > 0) {
            require_once __DIR__ . '/../Services/EspecialistaPricingService.php';
            $codigoEspecialista = (string)($tipoServico['code'] ?? '');
            $precoEspecialista = EspecialistaPricingService::calcular($codigoEspecialista, $distancia);
            if ($precoEspecialista !== null) {
                $origem = 'especialista_catalogo';
                $detalhe = $precoEspecialista['detalhe'];
                $custoFinal = (float)$precoEspecialista['customer_amount'];
            }
        }
        if ($latOrigem !== null && $lngOrigem !== null) {
            require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';
            $zonaPreco = ZonePricingService::calcularPreco($latOrigem, $lngOrigem, $serviceTypeId, $tipoVeiculoCru ?: null, $distancia);
            if ($detalhe === null && $zonaPreco !== null) {
                $origem = 'zona';
                $detalhe = $zonaPreco['detalhe'] + ['zona_nome' => $zonaPreco['zona_nome']];
                $custoFinal = $zonaPreco['valor'];
            }
        }
        if ($detalhe === null && $attendanceMode !== 'TOWING' && $serviceTypeId > 0) {
            require_once __DIR__ . '/../Models/Catalog/ServicePricingRule.php';
            $porServico = ServicePricingRule::calcularTotal($serviceTypeId, $distancia, null, $cidadeIdPreco);
            if ($porServico !== null) {
                $origem = 'servico';
                $detalhe = $porServico['detalhe'];
                $custoFinal = $porServico['valor'];
            }
        }
        if ($detalhe === null) {
            $origem = 'reboque';
            $detalhe = TarifaService::calcularDetalhado($distancia, $categoria, $prioridade, null, $cidadeIdPreco);
            $custoFinal = (float)$detalhe['valor'];
        }

        echo json_encode([
            'ok' => true,
            'custo' => (float)$custoFinal,
            'distancia' => $distancia,
            'origem' => $origem,
            'tarifa' => $detalhe,
        ], JSON_UNESCAPED_UNICODE);
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

    public function chatEnviar(int $pedidoId = 0): void
    {
        AuthService::requireAuth('cliente');
        header('Content-Type: application/json');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            // L1.9 #47: CSRF expirado deve disparar o fluxo de sessão expirada
            // no front (apiFetch → SessionManager.handleUnauthorized), não um
            // 200 com {ok:false} silencioso que o usuário só nota ao reparar
            // que a mensagem nunca chegou ao chat.
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.']); exit;
        }
        if ($pedidoId <= 0) {
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
        }
        $uid      = $this->usuarioId();
        $pedido   = Pedido::buscarPorId($pedidoId);
        $mensagem = trim($_POST['mensagem'] ?? '');
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']); exit;
        }
        require_once __DIR__ . '/../Services/ChatService.php';
        $resultado = (new ChatService())->sendMessage([
            'pedido_id' => $pedidoId,
            'usuario_id' => $uid,
            'mensagem' => $mensagem,
            'idempotency_key' => $_POST['idempotency_key'] ?? null,
        ]);
        echo json_encode(['ok' => $resultado['ok'], 'erro' => $resultado['erro'], 'id' => $resultado['id']]);
        exit;
    }

    public function chatMensagens(int $pedidoId): void
    {
        AuthService::requireAuth('cliente', false);
        header('Content-Type: application/json');
        $uid      = $this->usuarioId();
        $pedido   = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            echo json_encode(['ok' => false]); exit;
        }
        $desdeId   = (int)($_GET['desde_id'] ?? 0);
        require_once __DIR__ . '/../Services/ChatService.php';
        $chatService = new ChatService();
        $mensagens = $chatService->getMessagesByPedido($pedidoId, $desdeId);
        $chatService->marcarLidas($pedidoId, $uid);
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
        $especialistaId = 0;
        if (!empty($pedido['incidente_id'])) {
            $stEsp = getPDO()->prepare('SELECT especialista_id FROM atendimentos_especialista WHERE incidente_id=? ORDER BY id DESC LIMIT 1');
            $stEsp->execute([(int)$pedido['incidente_id']]); $especialistaId = (int)$stEsp->fetchColumn();
        }
        if ($especialistaId) {
            Avaliacao::criarEspecialista($pedidoId, $uid, $especialistaId, $estrelas, $coment);
        }
        if ($guinchoId && !$especialistaId) {
            // Busca o usuario_id do guincho para usar na FK de avaliacoes (references guinchos.id agora)
            Avaliacao::criar($pedidoId, $uid, $guinchoId, $estrelas, $coment);
            // Recalcula reputação
            require_once __DIR__ . '/../Models/Guincho.php';
            Guincho::atualizarReputacao($guinchoId);
        }
        $this->redirect('/cliente/historico?avaliado=1');
    }
}
