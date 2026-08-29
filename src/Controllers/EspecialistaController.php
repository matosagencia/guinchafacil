<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Models/Especialista.php';
require_once __DIR__ . '/../Services/EspecialistaAtendimentoService.php';
require_once __DIR__ . '/../Services/EspecialistaProofOfRoadService.php';

class EspecialistaController extends BaseController
{
    public function dashboard(): void
    {
        AuthService::requireAuth('especialista');
        $usuarioId = (int)($_SESSION['user']['id'] ?? 0);
        $especialista = Especialista::buscarPorUsuarioId($usuarioId);
        $bp = defined('BASE_PATH') ? BASE_PATH : '';
        $atendimentos = $especialista ? EspecialistaAtendimentoService::listarDoEspecialista((int)$especialista['id']) : [];
        require __DIR__ . '/../Views/especialista/dashboard.php';
    }

    public function perfilForm(): void
    {
        AuthService::requireAuth('especialista');
        $usuario = AuthService::getCurrentUser();
        $especialista = Especialista::buscarPorUsuarioId((int)$usuario['id']);
        if (!$especialista) { $this->redirect('/login'); }
        $stmt = getPDO()->prepare('SELECT id,nome,email,cpf,telefone FROM usuarios WHERE id=?');
        $stmt->execute([(int)$usuario['id']]);
        $dadosUsuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $csrfToken = AuthService::gerarCsrfToken();
        $flashMsg = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        require __DIR__ . '/../Views/especialista/perfil.php';
    }

    public function servicos(): void
    {
        AuthService::requireAuth('especialista');
        $usuario = AuthService::getCurrentUser();
        $especialista = Especialista::buscarPorUsuarioId((int)$usuario['id']);
        if (!$especialista) { $this->redirect('/login'); }
        $servicos = Especialista::servicosComCatalogo((int)$especialista['id']);
        $bp = defined('BASE_URL') ? BASE_URL : '';
        require __DIR__ . '/../Views/especialista/servicos.php';
    }

    public function perfilSalvar(): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $usuario = AuthService::getCurrentUser();
        $especialista = Especialista::buscarPorUsuarioId((int)$usuario['id']);
        if (!$especialista) { $this->redirect('/login'); }
        try {
            $nome = trim((string)($_POST['nome_profissional'] ?? ''));
            $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
            $bio = trim((string)($_POST['bio'] ?? ''));
            $raio = (float)($_POST['raio_atendimento_km'] ?? 10);
            $pix = trim((string)($_POST['chave_pix'] ?? ''));
            $pixTipo = (string)($_POST['chave_pix_tipo'] ?? 'cpf');
            if (mb_strlen($nome) < 3) throw new InvalidArgumentException('Informe um nome profissional válido.');
            if (strlen($telefone) < 10) throw new InvalidArgumentException('Informe um telefone válido.');
            if ($raio <= 0 || $raio > 200) throw new InvalidArgumentException('Raio de atendimento inválido.');
            if ($pix === '') throw new InvalidArgumentException('A chave Pix é obrigatória.');
            if (!in_array($pixTipo, ['cpf','cnpj','email','telefone','aleatoria'], true)) throw new InvalidArgumentException('Tipo de chave Pix inválido.');
            Especialista::atualizarPerfil((int)$especialista['id'], $nome, $bio, $raio, $pix, $pixTipo);
            getPDO()->prepare('UPDATE usuarios SET nome=?, telefone=? WHERE id=?')->execute([$nome, $telefone, (int)$usuario['id']]);
            $_SESSION['user']['nome'] = $nome;
            $_SESSION['_flash'][] = ['message' => 'Perfil atualizado. Se a chave Pix foi alterada, a nova chave entra em vigor após 24 horas.', 'type' => 'success'];
        } catch (Throwable $e) { $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error']; }
        $this->redirect('/especialista/perfil');
    }

    public function disponibilidade(): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e = Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id'] ?? 0));
        if (!$e) { http_response_code(404); exit; }
        getPDO()->prepare('UPDATE especialistas SET disponivel=? WHERE id=?')->execute([!empty($_POST['disponivel']) ? 1 : 0, (int)$e['id']]);
        $this->redirect('/especialista/dashboard');
    }

    public function aceitar(int $id = 0): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e = Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id'] ?? 0));
        if (!$e) { http_response_code(404); exit; }
        try { EspecialistaAtendimentoService::aceitar($id, (int)$e['id']); $this->redirect('/especialista/dashboard?msg=aceito'); }
        catch (Throwable $x) { $this->redirect('/especialista/dashboard?erro='.rawurlencode($x->getMessage())); }
    }

    public function transicionar(int $id = 0): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e = Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id'] ?? 0));
        if (!$e) { http_response_code(404); exit; }
        try { EspecialistaAtendimentoService::transicionar($id, (int)$e['id'], (string)($_POST['status'] ?? ''), ['aceito','a_caminho','no_local','em_diagnostico','aguardando_aprovacao','em_execucao']); $this->redirect('/especialista/dashboard'); }
        catch (Throwable $x) { $this->redirect('/especialista/dashboard?erro='.rawurlencode($x->getMessage())); }
    }

    public function diagnostico(int $id = 0): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e = Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id'] ?? 0));
        if (!$e) { http_response_code(404); exit; }
        $itens = is_array($_POST['itens'] ?? null) ? $_POST['itens'] : [];
        try { EspecialistaProofOfRoadService::armazenarEvidencia($id,(int)$e['id'],'diagnostico',$_FILES['foto_diagnostico']??[]); EspecialistaAtendimentoService::registrarDiagnostico($id, (int)$e['id'], (string)($_POST['resultado'] ?? ''), (string)($_POST['descricao'] ?? ''), $itens); $this->redirect('/especialista/dashboard?msg=diagnostico'); }
        catch (Throwable $x) { $this->redirect('/especialista/dashboard?erro='.rawurlencode($x->getMessage())); }
    }

    public function chegada(int $id = 0): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e=Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id']??0));
        try { EspecialistaProofOfRoadService::armazenarEvidencia($id,(int)$e['id'],'chegada',$_FILES['foto_chegada']??[]); EspecialistaProofOfRoadService::validarChegada($id,(int)$e['id'],(float)($_POST['lat']??0),(float)($_POST['lng']??0),(float)($_POST['accuracy']??0)); EspecialistaAtendimentoService::transicionar($id,(int)$e['id'],'no_local',['a_caminho']); $this->redirect('/especialista/dashboard?msg=chegada'); }
        catch(Throwable $x){$this->redirect('/especialista/dashboard?erro='.rawurlencode($x->getMessage()));}
    }

    public function localizacao(int $id = 0): void
    {
        AuthService::requireAuth('especialista');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $e=Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id']??0)); if (!$e) { http_response_code(404); exit; }
        try { EspecialistaProofOfRoadService::registrarEvento($id,(int)$e['id'],'gps',(float)($_POST['lat']??0),(float)($_POST['lng']??0),(float)($_POST['accuracy']??0)); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); }
        catch(Throwable $x){ http_response_code(422); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'erro'=>$x->getMessage()]); } exit;
    }

    public function notificacoes(): void
    {
        AuthService::requireAuth('especialista');
        $e=Especialista::buscarPorUsuarioId((int)($_SESSION['user']['id']??0));
        header('Content-Type: application/json; charset=UTF-8');
        if (!$e) { echo json_encode(['ok'=>false,'ofertas'=>[]]); exit; }
        $st=getPDO()->prepare("SELECT a.id,a.status,a.criado_em,s.nome AS servico_nome,i.endereco_origem FROM atendimentos_especialista a JOIN incidentes i ON i.id=a.incidente_id JOIN servicos_especialista s ON s.id=a.servico_solicitado_id WHERE a.especialista_id=? AND a.status IN ('ofertado','aceito','a_caminho','no_local','em_diagnostico','aguardando_aprovacao','em_execucao') ORDER BY a.criado_em DESC LIMIT 20");
        $st->execute([(int)$e['id']]); echo json_encode(['ok'=>true,'ofertas'=>$st->fetchAll(PDO::FETCH_ASSOC)?:[]],JSON_UNESCAPED_UNICODE); exit;
    }
}
