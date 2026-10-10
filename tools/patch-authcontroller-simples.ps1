# tools/patch-authcontroller-simples.ps1
# Adiciona registroSimples + perfilCompletar ao AuthController. ASCII-only. Faixa B.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$arquivo = Join-Path $Repositorio 'src\Controllers\AuthController.php'
if (-not (Test-Path -LiteralPath $arquivo)) { Write-Host "[ERRO] $arquivo"; exit 1 }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$arquivo.bak-simples-$stamp"
Copy-Item -LiteralPath $arquivo -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

if ($texto.Contains('public function registroSimplesForm(')) {
    Write-Host "[SKIP] registroSimplesForm ja existe." -ForegroundColor Cyan
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 0
}

# 1. Mudar gate de /auth/google/profile -> /perfil/completar
$antes = $texto.Length
$texto = $texto.Replace("`$this->redirect('/auth/google/profile');", "`$this->redirect('/perfil/completar');")
$delta = $antes - $texto.Length
Write-Host "[GATE] $delta byte(s) alterado(s) no redirectByProfile." -ForegroundColor Green

# 2. Apontar registroClienteForm / registroGuinchoForm para a view simples
$texto = $texto.Replace("require __DIR__ . '/../Views/auth/registrocliente.php';", "`$tipo = 'cliente'; require __DIR__ . '/../Views/auth/registro_simples.php';")
$texto = $texto.Replace("require __DIR__ . '/../Views/auth/registroguincho.php';", "`$tipo = 'guincho'; require __DIR__ . '/../Views/auth/registro_simples.php';")
Write-Host "[VIEW] registro*Form passam a usar registro_simples.php." -ForegroundColor Green

# 3. Inserir metodos antes do marcador ESQUECEU SENHA
$marker = '    // ' + [char]0x2500 + [char]0x2500 + [char]0x2500 + ' ESQUECEU SENHA'
$idx = $texto.IndexOf($marker)
if ($idx -lt 0) {
    $alt = '// ' + [char]0x2500 + [char]0x2500 + [char]0x2500 + ' ESQUECEU SENHA'
    $idx = $texto.IndexOf($alt)
}
if ($idx -lt 0) {
    Write-Host "[ERRO] marcador ESQUECEU SENHA nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force; exit 1
}

$novo = @'
    // --- SIGNUP SIMPLIFICADO (3 campos) + PERFIL INTERNO ---------------

    public function registroSimplesForm(string $tipo): void
    {
        if ($this->isAuthenticated()) { $this->redirectByProfile(); return; }
        if (!in_array($tipo, ['cliente','guincho','oficina'], true)) {
            $this->setFlashMessage('Tipo de cadastro invalido.', 'error');
            $this->redirect('/registro/cliente');
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        $retorno = AuthService::sanitizeReturnPath((string)($_GET['retorno'] ?? '/'));
        require __DIR__ . '/../Views/auth/registro_simples.php';
    }

    public function registroSimples(string $tipo): void
    {
        $tipo = in_array($tipo, ['cliente','guincho','oficina'], true) ? $tipo : 'cliente';
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/registro/simples/' . $tipo);
            return;
        }
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $telefone = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));

        $erros = [];
        if (mb_strlen($nome) < 3) $erros[] = 'Informe seu nome completo.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail invalido.';
        if (!$this->validarTelefoneBr($telefone)) $erros[] = 'WhatsApp invalido (DDD + numero).';
        if ($erros) {
            $this->setFlashMessage(implode(' ', $erros), 'error');
            $this->redirect('/registro/simples/' . $tipo);
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? FOR UPDATE');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                $this->setFlashMessage('Este e-mail ja tem conta. Faca login.', 'error');
                $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar'));
                return;
            }

            $senhaPlaceholder = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone, tipo, perfil_status, ativo, criado_em) VALUES (?, ?, ?, ?, ?, 'PENDENTE', 1, NOW())");
            $stmt->execute([$nome, $email, $senhaPlaceholder, $telefone, $tipo]);
            $userId = (int)$pdo->lastInsertId();

            $pdo->commit();

            $user = ['id' => $userId, 'nome' => $nome, 'email' => $email, 'tipo' => $tipo, 'ativo' => true];
            AuthService::initializeAuthenticatedSession($user);
            $this->setFlashMessage('Conta criada. Complete seus dados para liberar o painel.', 'success');
            $this->redirect('/perfil/completar');
            return;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[registroSimples] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel criar a conta agora.', 'error');
            $this->redirect('/registro/simples/' . $tipo);
        }
    }

    public function perfilCompletarForm(): void
    {
        if (!$this->isAuthenticated()) { $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar')); return; }
        $user = AuthService::getCurrentUser();
        if (!$user) { $this->redirect('/login'); return; }

        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id, nome, email, telefone, cpf, tipo, perfil_status FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (($u['perfil_status'] ?? '') === 'COMPLETO') { $this->redirectByProfile((string)$u['tipo']); return; }

        $tipo = in_array((string)($u['tipo'] ?? 'cliente'), ['cliente','guincho','oficina','especialista'], true) ? (string)$u['tipo'] : 'cliente';
        $salvo = [
            'nome' => $u['nome'] ?? '',
            'telefone' => $u['telefone'] ?? '',
            'cpf' => $u['cpf'] ?? '',
            'estado' => '',
        ];
        $pendenteAprovacao = (($u['perfil_status'] ?? '') === 'PENDENTE_APROVACAO');
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/auth/perfil_completar.php';
    }

    public function perfilCompletarSave(): void
    {
        if (!$this->isAuthenticated() || !$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sessao expirada. Entre novamente.', 'error');
            $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar'));
            return;
        }
        $user = AuthService::getCurrentUser();
        if (!$user) { $this->redirect('/login'); return; }

        $nome = trim((string)($_POST['nome'] ?? ''));
        $cpf = preg_replace('/\D/', '', (string)($_POST['cpf'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');
        $conf = (string)($_POST['confirmar_senha'] ?? '');
        $cep = preg_replace('/\D/', '', (string)($_POST['cep'] ?? ''));
        $logradouro = trim((string)($_POST['logradouro'] ?? ''));
        $numero = trim((string)($_POST['numero'] ?? ''));
        $bairro = trim((string)($_POST['bairro'] ?? ''));
        $cidade = trim((string)($_POST['cidade'] ?? ''));
        $estado = strtoupper(trim((string)($_POST['estado'] ?? '')));

        $erros = [];
        if (mb_strlen($nome) < 3) $erros[] = 'Nome invalido.';
        if (!$this->validarCPF($cpf)) $erros[] = 'CPF invalido.';
        if (!$this->validarTelefoneBr($telefone)) $erros[] = 'Telefone invalido.';
        if (strlen($senha) < 8) $erros[] = 'Senha deve ter 8+ caracteres.';
        if ($senha !== $conf) $erros[] = 'Senhas nao conferem.';
        if (strlen($cep) !== 8) $erros[] = 'CEP invalido.';
        if ($logradouro === '' || $numero === '' || $bairro === '' || $cidade === '') $erros[] = 'Endereco incompleto.';
        if (!preg_match('/^[A-Z]{2}$/', $estado)) $erros[] = 'UF invalida.';

        if ($erros) {
            $this->setFlashMessage(implode(' ', $erros), 'error');
            $this->redirect('/perfil/completar');
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1 FOR UPDATE');
            $dup->execute([$cpf, (int)$user['id']]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                $this->setFlashMessage('Este CPF ja esta em outra conta.', 'error');
                $this->redirect('/perfil/completar');
                return;
            }

            $tipoAtual = (string)($_SESSION['user']['tipo'] ?? 'cliente');
            $novoStatus = ($tipoAtual === 'cliente') ? 'COMPLETO' : 'PENDENTE_APROVACAO';

            $pdo->prepare('UPDATE usuarios SET nome = ?, cpf = ?, telefone = ?, senha_hash = ?, perfil_status = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$nome, $cpf, $telefone, password_hash($senha, PASSWORD_BCRYPT), $novoStatus, (int)$user['id']]);

            $temEnd = $pdo->prepare('SELECT id FROM enderecos WHERE usuario_id = ? LIMIT 1');
            $temEnd->execute([(int)$user['id']]);
            if ($temEnd->fetch()) {
                $pdo->prepare('UPDATE enderecos SET cep = ?, logradouro = ?, numero = ?, bairro = ?, cidade = ?, estado = ? WHERE usuario_id = ?')
                    ->execute([$cep, $logradouro, $numero, $bairro, $cidade, $estado, (int)$user['id']]);
            } else {
                $pdo->prepare('INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)')
                    ->execute([(int)$user['id'], $cep, $logradouro, $numero, trim((string)($_POST['complemento'] ?? '')) ?: null, $bairro, $cidade, $estado]);
            }

            $_SESSION['user']['nome'] = $nome;
            $_SESSION['user']['perfil_status'] = $novoStatus;

            $pdo->commit();

            if ($novoStatus === 'COMPLETO') {
                $this->setFlashMessage('Perfil completo. Bem-vindo!', 'success');
                $this->redirectByProfile($tipoAtual);
            } else {
                $this->setFlashMessage('Dados recebidos. Sua documentacao sera analisada pela equipe.', 'success');
                $this->redirect('/perfil/completar');
            }
            return;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[perfilCompletarSave] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel salvar. Tente novamente.', 'error');
            $this->redirect('/perfil/completar');
        }
    }

'@

# Reinsere o marcador original depois do bloco novo
$texto = $texto.Substring(0, $idx) + $novo + $texto.Substring($idx)

[System.IO.File]::WriteAllText($arquivo, $texto, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}
Write-Host ""
Write-Host "[DONE] AuthController atualizado." -ForegroundColor Green
Write-Host "Backup: $bak" -ForegroundColor Yellow
Write-Host "Reverter: Copy-Item '$bak' '$arquivo' -Force"