<?php
// File: guinchafacil/src/Services/UserService.php

class UserService
{
    private $usuarioModel;

    public function __construct()
    {
        // getPDO via BaseController->require Database.php ou outro include
        $this->usuarioModel = new Usuario();
    }

    public function emailExists(string $email): bool
    {
        $u = $this->usuarioModel->buscarPorEmail($email);
        return !empty($u);
    }

    /**
     * Autentica por email/senha (senha em texto puro).
     * Retorna array do usuário ou false.
     */
    public function authenticate(string $email, string $senha)
    {
        $user = $this->usuarioModel->buscarPorEmail($email);
        if (!$user) {
            return false;
        }

        $hash = $user['senha_hash'] ?? '';
        if (!$hash || !password_verify($senha, $hash)) {
            return false;
        }

        // não devolve hash para o controller
        unset($user['senha_hash']);
        return $user;
    }

    /**
     * Cria usuário.
     * Espera $dados['senha'] (hash ou texto puro) ou $dados['senha_hash'].
     * Retorna ID inserido.
     */
    public function create(array $dados): int
    {
        $senha = $dados['senha_hash'] ?? ($dados['senha'] ?? '');
        if (is_string($senha) && $senha !== '') {
            // Se não parece hash bcrypt/argon, gera hash
            if (strpos($senha, '$2y$') !== 0 && strpos($senha, '$argon2') !== 0) {
                $senha = password_hash($senha, PASSWORD_DEFAULT);
            }
        }

        $payload = [
            'nome'      => $dados['nome'] ?? '',
            'email'     => $dados['email'] ?? '',
            'senha_hash'=> $senha,
            'telefone'  => $dados['telefone'] ?? '',
            'cpf'       => $dados['cpf'] ?? '',
            'tipo'      => $dados['tipo'] ?? 'cliente',
            'ativo'     => isset($dados['ativo']) ? (int)$dados['ativo'] : 1,
        ];

        $id = (int)$this->usuarioModel->criar($payload);

        // Hack compatível: permite EnderecoService pegar o user_id mesmo que não seja passado.
        $GLOBALS['__GF_LAST_USER_ID'] = $id;

        return $id;
    }

    public function updateLastLogin(int $userId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
}
