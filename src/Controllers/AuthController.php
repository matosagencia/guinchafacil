<?php
// File: guinchafacil/src/Controllers/AuthController.php
// Refatorado com transações seguras e validações aprimoradas

class AuthController extends BaseController
{
    private const UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf'
    ];

    public function __construct() 
    { 
        parent::__construct(); 
    }

    // ─── LOGIN ────────────────────────────────────────────────────
    public function loginForm(): void
    {
        if ($this->isAuthenticated()) { 
            $this->redirectByProfile(); 
            return; 
        }
        
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/auth/login.php';
    }

    public function login(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/login'); 
            return;
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = $_POST['senha'] ?? $_POST['password'] ?? '';

        if (!$email || empty($senha)) {
            $this->setFlashMessage('Email e senha são obrigatórios.', 'error');
            $this->redirect('/login'); 
            return;
        }

        $pdo = getPDO();
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($senha, $user['senha_hash'] ?? '')) {
                $this->setFlashMessage('Email ou senha incorretos.', 'error');
                $this->redirect('/login'); 
                return;
            }

            // Atualiza último login
            $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Cria sessão com regeneração segura
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => (int)$user['id'],
                'nome'  => $user['nome'],
                'email' => $user['email'],
                'tipo'  => $user['tipo'],
                'ativo' => (bool)$user['ativo'],
            ];
            $_SESSION['auth_at'] = time();
            $_SESSION['last_regen'] = time();

            $this->redirectByProfile($user['tipo']);
            
        } catch (Throwable $e) {
            error_log("Erro no login: " . $e->getMessage());
            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');
            $this->redirect('/login');
        }
    }

    // ─── LOGOUT ───────────────────────────────────────────────────
    public function logout(): void
    {
        $_SESSION = [];
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $this->redirect('/login');
    }

    // ─── REGISTRO CLIENTE ─────────────────────────────────────────
    public function registroClienteForm(): void
    {
        if ($this->isAuthenticated()) { 
            $this->redirectByProfile(); 
            return; 
        }
        
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/auth/registrocliente.php';
    }

    public function registroCliente(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/cliente'); 
            return;
        }

        // Sanitização dos dados
        $dados = $this->sanitizarDadosCliente($_POST);
        $erros = $this->validarDadosCliente($dados);

        if (!empty($erros)) {
            $this->setFlashMessage(implode('<br>', $erros), 'error');
            $this->redirect('/registro/cliente'); 
            return;
        }

        $pdo = getPDO();
        
        try {
            $pdo->beginTransaction();

            // Verifica duplicatas com lock
            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");
            $dup->execute([$dados['email'], $dados['cpf']]);
            
            if ($dup->fetch()) {
                throw new Exception('Email ou CPF já cadastrado.');
            }

            // Cria usuário
            $userId = $this->criarUsuario($pdo, $dados, 'cliente');

            // Cria endereço principal
            $this->criarEndereco($pdo, $userId, $dados, true);

            $pdo->commit();
            
            $this->setFlashMessage('Cadastro realizado! Faça login para continuar.', 'success');
            $this->redirect('/login');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro registroCliente: " . $e->getMessage());
            $this->setFlashMessage($e->getMessage() ?: 'Erro interno. Tente novamente.', 'error');
            $this->redirect('/registro/cliente');
        }
    }

    // ─── REGISTRO GUINCHO ─────────────────────────────────────────
    public function registroGuinchoForm(): void
    {
        if ($this->isAuthenticated()) { 
            $this->redirectByProfile(); 
            return; 
        }
        
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/auth/registroguincho.php';
    }

    public function registroGuincho(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/guincho'); 
            return;
        }

        // Sanitização dos dados
        $dados = $this->sanitizarDadosGuincho($_POST);
        $erros = $this->validarDadosGuincho($dados, $_FILES);

        if (!empty($erros)) {
            $this->setFlashMessage(implode('<br>', $erros), 'error');
            $this->redirect('/registro/guincho'); 
            return;
        }

        $pdo = getPDO();
        
        try {
            $pdo->beginTransaction();

            // Verifica duplicatas com lock
            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");
            $dup->execute([$dados['email'], $dados['cpf']]);
            
            if ($dup->fetch()) {
                throw new Exception('Email ou CPF já cadastrado.');
            }

            // Verifica placa duplicada
            $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? FOR UPDATE");
            $dupPlaca->execute([$dados['placa_guincho']]);
            
            if ($dupPlaca->fetch()) {
                throw new Exception('Placa já cadastrada no sistema.');
            }

            // Processa uploads
            $uploads = $this->processarUploads($_FILES);

            // Cria usuário
            $userId = $this->criarUsuario($pdo, $dados, 'guincho');

            // Cria registro do guincho
            $guinchoData = [
                'usuario_id' => $userId,
                'cnh_numero' => $dados['cnh_numero'],
                'cnh_validade' => $dados['cnh_validade'],
                'placa_guincho' => $dados['placa_guincho'],
                'capacidade_ton' => $dados['capacidade_ton'], // coluna real no banco é capacidade_ton
                'raio_cobertura_km' => $dados['raio_cobertura_km'] ?? 20,
                'chave_pix' => $dados['chave_pix'],
                'chave_pix_tipo' => $dados['chave_pix_tipo'],
                'foto_veiculo' => $uploads['foto_veiculo'] ?? null,
                'doc_cnh_frente' => $uploads['doc_cnh_frente'] ?? null,
                'doc_cnh_verso' => $uploads['doc_cnh_verso'] ?? null,
                'aprovado' => 0,
                'disponivel' => 0,
                'lat_atual' => null, // coluna real no banco é lat_atual
                'lng_atual' => null  // coluna real no banco é lng_atual
            ];

            $guinchoId = $this->criarGuincho($pdo, $guinchoData);
            
            if (!$guinchoId) {
                throw new Exception('Falha ao criar registro do guincho');
            }

            $pdo->commit();
            
            $this->setFlashMessage('Cadastro enviado! Aguarde a aprovação do administrador.', 'success');
            $this->redirect('/login');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $this->limparUploads($uploads ?? []);
            error_log("Erro registroGuincho: " . $e->getMessage());
            $this->setFlashMessage($e->getMessage() ?: 'Erro interno. Tente novamente.', 'error');
            $this->redirect('/registro/guincho');
        }
    }

    // ─── ESQUECEU SENHA ──────────────────────────────────────────
    public function esqueceuSenhaForm(): void
    {
        if ($this->isAuthenticated()) { 
            $this->redirectByProfile(); 
            return; 
        }
        
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/auth/esqueceu_senha.php';
    }

    public function esqueceuSenha(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/senha/esqueceu'); 
            return;
        }
        
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $this->setFlashMessage('Se esse email existir, enviaremos um link de redefinição.', 'success');

        if ($email) {
            $pdo = getPDO();
            
            try {
                $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                    
                    $token = bin2hex(random_bytes(32));
                    $expira = date('Y-m-d H:i:s', time() + 3600);
                    
                    $pdo->prepare("INSERT INTO password_resets (email, token, expira_em) VALUES (?,?,?)")
                        ->execute([$email, $token, $expira]);
                    
                    $link = (defined('APP_URL') ? APP_URL : '') . '/senha/redefinir/' . $token;
                    
                    $corpo = "<p>Olá, <b>" . htmlspecialchars($user['nome']) . "</b>!</p>
                             <p>Recebemos uma solicitação para redefinir sua senha.</p>
                             <p><a href='$link'>Clique aqui para redefinir sua senha</a></p>
                             <p>Este link é válido por 1 hora.</p>
                             <p>Se não foi você quem solicitou, ignore este email.</p>";
                    
                    $this->enviarEmail(
                        $email, 
                        'Redefinição de senha — GuinchaFácil', 
                        $corpo
                    );
                }
            } catch (Throwable $e) {
                error_log("Erro esqueceuSenha: " . $e->getMessage());
            }
        }
        
        $this->redirect('/login');
    }

    public function redefinirSenhaForm(string $token): void
    {
        $pdo = getPDO();
        
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM password_resets 
                 WHERE token = ? AND usado = 0 AND expira_em > NOW() 
                 LIMIT 1"
            );
            $stmt->execute([$token]);
            
            if (!$stmt->fetch()) {
                $this->setFlashMessage('Link inválido ou expirado.', 'error');
                $this->redirect('/senha/esqueceu'); 
                return;
            }
            
            $csrf_token = $this->generateCSRFToken();
            require __DIR__ . '/../Views/auth/redefinir_senha.php';
            
        } catch (Throwable $e) {
            error_log("Erro redefinirSenhaForm: " . $e->getMessage());
            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');
            $this->redirect('/senha/esqueceu');
        }
    }

    public function redefinirSenha(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/login'); 
            return;
        }
        
        $token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirma = $_POST['confirmacao'] ?? '';

        if (strlen($senha) < 8 || $senha !== $confirma) {
            $this->setFlashMessage('Senha inválida ou as senhas não conferem (mínimo 8 caracteres).', 'error');
            $this->redirect('/senha/redefinir/' . $token); 
            return;
        }

        $pdo = getPDO();
        
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM password_resets 
                 WHERE token = ? AND usado = 0 AND expira_em > NOW() 
                 LIMIT 1"
            );
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                $this->setFlashMessage('Link inválido ou expirado.', 'error');
                $this->redirect('/senha/esqueceu'); 
                return;
            }

            $pdo->beginTransaction();

            // Atualiza senha
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE email = ?")
                ->execute([password_hash($senha, PASSWORD_BCRYPT), $reset['email']]);
            
            // Marca token como usado
            $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?")
                ->execute([$token]);

            $pdo->commit();
            
            $this->setFlashMessage('Senha redefinida com sucesso!', 'success');
            $this->redirect('/login');
            
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("Erro redefinirSenha: " . $e->getMessage());
            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');
            $this->redirect('/senha/redefinir/' . $token);
        }
    }

    // ─── MÉTODOS PRIVADOS DE NEGÓCIO ─────────────────────────────

    private function criarUsuario(PDO $pdo, array $dados, string $tipo): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        
        $stmt->execute([
            $dados['nome'],
            $dados['email'],
            password_hash($dados['senha'], PASSWORD_BCRYPT),
            $dados['telefone'],
            $dados['cpf'],
            $tipo
        ]);
        
        return (int)$pdo->lastInsertId();
    }

    private function criarEndereco(PDO $pdo, int $usuarioId, array $dados, bool $principal): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $usuarioId,
            $dados['cep'],
            $dados['logradouro'],
            $dados['numero'],
            $dados['complemento'] ?? null,
            $dados['bairro'],
            $dados['cidade'],
            $dados['estado'],
            $principal ? 1 : 0
        ]);
        
        return (int)$pdo->lastInsertId();
    }

    private function criarGuincho(PDO $pdo, array $dados): ?int
    {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO guinchos (
                    usuario_id, cnh_numero, cnh_validade, placa_guincho,
                    capacidade_ton, raio_cobertura_km, chave_pix, chave_pix_tipo,
                    foto_veiculo, doc_cnh_frente, doc_cnh_verso,
                    aprovado, disponivel, lat_atual, lng_atual, criado_em
                ) VALUES (
                    :usuario_id, :cnh_numero, :cnh_validade, :placa_guincho,
                    :capacidade_ton, :raio_cobertura_km, :chave_pix, :chave_pix_tipo,
                    :foto_veiculo, :doc_cnh_frente, :doc_cnh_verso,
                    :aprovado, :disponivel, :lat_atual, :lng_atual, NOW()
                )"
            );

            $stmt->execute([
                ':usuario_id'       => $dados['usuario_id'],
                ':cnh_numero'       => $dados['cnh_numero'],
                ':cnh_validade'     => $dados['cnh_validade'],
                ':placa_guincho'    => $dados['placa_guincho'],
                ':capacidade_ton'   => $dados['capacidade_ton'],
                ':raio_cobertura_km'=> $dados['raio_cobertura_km'],
                ':chave_pix'        => $dados['chave_pix'],
                ':chave_pix_tipo'   => $dados['chave_pix_tipo'],
                ':foto_veiculo'     => $dados['foto_veiculo'],
                ':doc_cnh_frente'   => $dados['doc_cnh_frente'],
                ':doc_cnh_verso'    => $dados['doc_cnh_verso'],
                ':aprovado'         => $dados['aprovado'],
                ':disponivel'       => $dados['disponivel'],
                ':lat_atual'        => $dados['lat_atual'],
                ':lng_atual'        => $dados['lng_atual']
            ]);

            return (int)$pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Erro PDO ao criar guincho: [" . $e->getCode() . "] " . $e->getMessage());
            throw new Exception('Falha ao salvar dados do guincho no banco: ' . $e->getMessage(), 0, $e);
        }
    }

    private function enviarEmail(string $para, string $assunto, string $corpo): bool
    {
        // ATENÇÃO: mail() nativo raramente funciona em hospedagens modernas sem SMTP configurado.
        // Para produção, substitua por PHPMailer ou SMTP via config.php (defina SMTP_HOST, SMTP_USER, etc.)
        // Exemplo: composer require phpmailer/phpmailer
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: GuinchaFácil <noreply@guinchafacil.com.br>',
            'Reply-To: suporte@guinchafacil.com.br',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($para, $assunto, $corpo, implode("\r\n", $headers));
    }

    // ─── MÉTODOS DE SANITIZAÇÃO ──────────────────────────────────

    private function sanitizarDadosCliente(array $post): array
    {
        return [
            'nome' => trim($post['nome'] ?? ''),
            'email' => strtolower(trim($post['email'] ?? '')),
            'telefone' => preg_replace('/\D/', '', $post['telefone'] ?? ''),
            'cpf' => preg_replace('/\D/', '', $post['cpf'] ?? ''),
            'senha' => $post['senha'] ?? '',
            'confirmar_senha' => $post['confirmar_senha'] ?? '',
            'cep' => preg_replace('/\D/', '', $post['cep'] ?? ''),
            'logradouro' => trim($post['logradouro'] ?? ''),
            'numero' => trim($post['numero'] ?? ''),
            'complemento' => trim($post['complemento'] ?? ''),
            'bairro' => trim($post['bairro'] ?? ''),
            'cidade' => trim($post['cidade'] ?? ''),
            'estado' => strtoupper(trim($post['estado'] ?? ''))
        ];
    }

    private function sanitizarDadosGuincho(array $post): array
    {
        $dados = $this->sanitizarDadosCliente($post);
        
        $dados['cnh_numero'] = preg_replace('/\D/', '', $post['cnh_numero'] ?? '');
        $dados['cnh_validade'] = trim($post['cnh_validade'] ?? '');
        $dados['placa_guincho'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $post['placa_guincho'] ?? ''));
        $dados['capacidade_ton'] = (float)($post['capacidade_ton'] ?? 0);
        $dados['raio_cobertura_km'] = (int)($post['raio_cobertura_km'] ?? 20);
        $dados['chave_pix'] = trim($post['chave_pix'] ?? '');
        $dados['chave_pix_tipo'] = $this->validarTipoPix($post['chave_pix_tipo'] ?? '');
        
        return $dados;
    }

    private function validarTipoPix(string $tipo): string
    {
        $tiposPermitidos = ['cpf', 'email', 'telefone', 'aleatoria'];
        return in_array($tipo, $tiposPermitidos, true) ? $tipo : 'cpf';
    }

    // ─── MÉTODOS DE VALIDAÇÃO ────────────────────────────────────

    private function validarDadosCliente(array $dados): array
    {
        $erros = [];

        if (mb_strlen($dados['nome']) < 3) {
            $erros[] = 'Nome deve ter pelo menos 3 caracteres.';
        }

        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Email inválido.';
        }

        if (strlen($dados['telefone']) < 10 || strlen($dados['telefone']) > 11) {
            $erros[] = 'Telefone inválido (DDD + número).';
        }

        if (!$this->validarCPF($dados['cpf'])) {
            $erros[] = 'CPF inválido.';
        }

        if (strlen($dados['senha']) < 6) {
            $erros[] = 'Senha deve ter pelo menos 6 caracteres.';
        }

        if ($dados['senha'] !== $dados['confirmar_senha']) {
            $erros[] = 'As senhas não conferem.';
        }

        if (strlen($dados['cep']) !== 8) {
            $erros[] = 'CEP inválido (deve conter 8 dígitos).';
        }

        if (empty($dados['logradouro']) || empty($dados['numero']) || 
            empty($dados['bairro']) || empty($dados['cidade']) || empty($dados['estado'])) {
            $erros[] = 'Preencha todos os campos de endereço.';
        }

        if (strlen($dados['estado']) !== 2) {
            $erros[] = 'UF deve ter 2 caracteres.';
        }

        return $erros;
    }

    private function validarDadosGuincho(array $dados, array $files): array
    {
        $erros = $this->validarDadosCliente($dados);

        if (empty($dados['cnh_numero'])) {
            $erros[] = 'Número da CNH é obrigatório.';
        } elseif (strlen($dados['cnh_numero']) < 9) {
            $erros[] = 'Número da CNH inválido.';
        }

        if (empty($dados['cnh_validade'])) {
            $erros[] = 'Data de validade da CNH é obrigatória.';
        } else {
            $validade = DateTime::createFromFormat('Y-m-d', $dados['cnh_validade']);
            $hoje = new DateTime();
            $hoje->setTime(0, 0, 0);
            
            if (!$validade) {
                $erros[] = 'Formato de data da CNH inválido. Use AAAA-MM-DD.';
            } elseif ($validade <= $hoje) {
                $erros[] = 'CNH deve ter validade futura.';
            }
        }

        if (strlen($dados['placa_guincho']) < 7) {
            $erros[] = 'Placa do guincho inválida (mínimo 7 caracteres).';
        } elseif (!preg_match('/^[A-Z0-9]{7}$/', $dados['placa_guincho'])) {
            $erros[] = 'Placa deve conter 7 caracteres alfanuméricos.';
        }

        if ($dados['capacidade_ton'] <= 0) {
            $erros[] = 'Capacidade em toneladas deve ser maior que zero.';
        } elseif ($dados['capacidade_ton'] > 100) {
            $erros[] = 'Capacidade máxima é 100 toneladas.';
        }

        if (empty($dados['chave_pix'])) {
            $erros[] = 'Chave PIX é obrigatória.';
        } elseif (!$this->validarChavePix($dados['chave_pix'], $dados['chave_pix_tipo'])) {
            $erros[] = 'Chave PIX inválida para o tipo selecionado.';
        }

        // Validações de arquivos
        $camposObrigatorios = ['doc_cnh_frente', 'doc_cnh_verso'];
        foreach ($camposObrigatorios as $campo) {
            if (!isset($files[$campo]) || $files[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
                $erros[] = 'Documento da CNH (frente e verso) é obrigatório.';
                break;
            }
        }

        return $erros;
    }

    private function validarChavePix(string $chave, string $tipo): bool
    {
        return match($tipo) {
            'cpf' => $this->validarCPF(preg_replace('/\D/', '', $chave)),
            'email' => filter_var($chave, FILTER_VALIDATE_EMAIL) !== false,
            'telefone' => strlen(preg_replace('/\D/', '', $chave)) >= 10,
            'aleatoria' => strlen($chave) >= 8 && strlen($chave) <= 32,
            default => false
        };
    }

    private function validarCPF(string $cpf): bool
    {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }

    // ─── MÉTODOS DE UPLOAD ───────────────────────────────────────

    private function processarUploads(array $files): array
    {
        $uploads = [];
        $campos = ['foto_veiculo', 'doc_cnh_frente', 'doc_cnh_verso'];

        foreach ($campos as $campo) {
            if (isset($files[$campo]) && $files[$campo]['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploads[$campo] = $this->processarUpload($campo);
                } catch (Exception $e) {
                    $this->limparUploads($uploads);
                    throw $e;
                }
            }
        }

        return $uploads;
    }

    private function processarUpload(string $campo): string
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Arquivo não enviado corretamente");
        }

        $file = $_FILES[$campo];

        // Valida tamanho
        if ($file['size'] > self::UPLOAD_MAX_SIZE) {
            throw new Exception("Arquivo muito grande (máximo 5MB)");
        }

        // Valida tipo MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!isset(self::ALLOWED_MIME_TYPES[$ext])) {
            throw new Exception("Extensão de arquivo não permitida");
        }
        
        if (self::ALLOWED_MIME_TYPES[$ext] !== $mimeType) {
            throw new Exception("Tipo de arquivo inválido");
        }

        // Gera nome seguro
        $destDir = $this->getUploadDir();
        $fileName = sprintf(
            '%s_%s_%s.%s',
            $campo,
            date('YmdHis'),
            bin2hex(random_bytes(4)),
            $ext
        );
        
        $destPath = $destDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception("Falha ao salvar arquivo");
        }

        // Define permissões seguras
        chmod($destPath, 0644);

        return $fileName;
    }

    private function getUploadDir(): string
    {
        $destDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 3) . '/uploads');
        
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                throw new Exception("Não foi possível criar diretório de uploads");
            }
        }
        
        if (!is_writable($destDir)) {
            throw new Exception("Diretório de uploads sem permissão de escrita");
        }
        
        return $destDir;
    }

    private function limparUploads(array $uploads): void
    {
        $destDir = $this->getUploadDir();
        
        foreach ($uploads as $arquivo) {
            if ($arquivo) {
                $caminho = $destDir . '/' . $arquivo;
                if (file_exists($caminho)) {
                    @unlink($caminho);
                }
            }
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────
    
    private function redirectByProfile(?string $tipo = null): void
    {
        $tipo = $tipo ?? ($_SESSION['user']['tipo'] ?? null);
        
        $dest = match($tipo) {
            'admin'   => '/admin/dashboard',
            'guincho' => '/guincho/dashboard',
            'cliente' => '/cliente/dashboard',
            default   => '/login',
        };
        
        $this->redirect($dest);
    }

    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user']) && isset($_SESSION['auth_at']) && 
               (time() - $_SESSION['auth_at']) < 3600; // 1 hora de sessão
    }

    protected function generateCSRFToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    protected function validateCSRFToken(string $token): bool
    {
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    protected function setFlashMessage(string $message, string $type = 'info'): void
    {
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][] = [
            'message' => $message,
            'type' => $type,
            'time' => time()
        ];
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}