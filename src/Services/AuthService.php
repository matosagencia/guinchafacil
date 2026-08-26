<?php
/**
 * AuthService — verifica autenticação via $_SESSION['user']
 * (chave gravada por AuthController::createUserSession)
 */
class AuthService
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) return null;

        if (!empty($_SESSION['user']['id'])) {
            return $_SESSION['user'];
        }

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT id, nome, email, tipo, ativo FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) { $_SESSION['user'] = $user; return $user; }
        } catch (\PDOException $e) {
            error_log('[AuthService] getCurrentUser: ' . $e->getMessage());
        }
        return null;
    }

    /** §5.1: Sessão expira após 60 minutos de inatividade */
    private const INACTIVITY_TIMEOUT = 3600;

    public static function requireAuth(?string $perfil = null): array
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login');
            exit;
        }

        // §5.1: verifica inatividade
        if (isset($_SESSION['_last_activity']) && (time() - (int)$_SESSION['_last_activity']) > self::INACTIVITY_TIMEOUT) {
            session_unset(); session_destroy();
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login?motivo=inatividade');
            exit;
        }
        $_SESSION['_last_activity'] = time();

        $user = self::getCurrentUser();
        if (!$user) {
            session_unset(); session_destroy();
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login');
            exit;
        }
        if ($perfil !== null && ($user['tipo'] ?? '') !== $perfil) {
            http_response_code(403); echo '<h1>403 — Acesso negado</h1>'; exit;
        }
        return $user;
    }

    public static function login(string $email, string $senha): array
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!self::verificarRateLimit($ip, 'login')) {
            return ['success' => false, 'message' => 'Muitas tentativas. Tente em 5 minutos.'];
        }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT id, nome, email, senha_hash, tipo, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1"
            );
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                self::registrarTentativa($ip, 'login');
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$usuario['id']]);
            session_regenerate_id(true);
            $sessionUser = [
                'id'    => $usuario['id'],
                'nome'  => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo'  => $usuario['tipo'],
                'ativo' => $usuario['ativo'],
            ];
            $_SESSION['user']           = $sessionUser;
            $_SESSION['user_id']        = $usuario['id'];
            $_SESSION['user_tipo']      = $usuario['tipo'];
            $_SESSION['_last_activity'] = time(); // §5.1: inicia contador de inatividade
            return ['success' => true, 'message' => 'Login realizado com sucesso.'];
        } catch (\PDOException $e) {
            error_log('[AuthService] login: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public static function verificarRateLimit(string $ip, string $rota): bool
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT tentativas, bloqueado_ate FROM rate_limit WHERE ip = ? AND rota = ? LIMIT 1");
            $stmt->execute([$ip, $rota]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                if (!empty($r['bloqueado_ate']) && strtotime($r['bloqueado_ate']) > time()) return false;
                if ((int)$r['tentativas'] >= 5) {
                    $pdo->prepare("UPDATE rate_limit SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE ip = ? AND rota = ?")->execute([$ip, $rota]);
                    return false;
                }
            }
        } catch (\PDOException $e) {
            error_log('[AuthService] rateLimit: ' . $e->getMessage());
            return true;
        }
        return true;
    }

    private static function registrarTentativa(string $ip, string $rota): void
    {
        try {
            $pdo = getPDO();
            $pdo->prepare("INSERT INTO rate_limit (ip, rota, tentativas) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE tentativas = tentativas + 1")->execute([$ip, $rota]);
        } catch (\PDOException $e) {
            error_log('[AuthService] registrarTentativa: ' . $e->getMessage());
        }
    }

    // Aliases para compatibilidade com controllers que chamam AuthService::gerarCsrfToken etc.
    public static function gerarCsrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function validarCsrfToken(string $token): bool
    {
        if (empty($_SESSION['_csrf_token'])) return false;
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

}
