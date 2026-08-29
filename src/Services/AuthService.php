<?php
require_once __DIR__ . '/RequestIpResolver.php';

/**
 * AuthService — autenticação, expiração previsível e respostas JSON para APIs.
 */
class AuthService
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);
    }

    public static function isJsonRequest(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return $xhr === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false
            || preg_match('~/(status|status-json|pedidos-disponiveis|chat|cancelar|disponibilidade|localizacao|session-status)(/|$|\?)~', $uri) === 1;
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) return null;

        if (!empty($_SESSION['user']['id'])) {
            return $_SESSION['user'];
        }

        try {
            $stmt = getPDO()->prepare(
                'SELECT id, nome, email, tipo, ativo FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user'] = $user;
                return $user;
            }
        } catch (PDOException $e) {
            error_log('[AuthService][getCurrentUser][database] ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Exige autenticação.
     * $refreshActivity=false deve ser usado em polling/consultas passivas.
     */
    public static function requireAuth(?string $perfil = null, bool $refreshActivity = true): array
    {
        self::assertSessionValid($refreshActivity);

        $user = self::getCurrentUser();
        if (!$user) {
            self::destroySession();
            self::unauthorized('sessao_invalida');
        }

        // Mantém compatibilidade: admin pode visualizar áreas cliente/guincho.
        $tipo = (string)($user['tipo'] ?? '');
        if ($perfil !== null && $tipo !== $perfil && $tipo !== 'admin') {
            self::forbidden();
        }

        return $user;
    }

    public static function assertSessionValid(bool $refreshActivity = false): void
    {
        if (!self::isLoggedIn()) {
            self::unauthorized('nao_autenticado');
        }

        $now = time();
        $startedAt = (int)($_SESSION['_auth_started_at'] ?? $_SESSION['auth_at'] ?? $now);
        $lastActivity = (int)($_SESSION['_last_activity'] ?? $startedAt);
        $idleTimeout = defined('SESSION_IDLE_TIMEOUT') ? max(60, (int)SESSION_IDLE_TIMEOUT) : 3600;
        $absoluteTimeout = defined('SESSION_ABSOLUTE_TIMEOUT') ? max($idleTimeout, (int)SESSION_ABSOLUTE_TIMEOUT) : 43200;

        if (($now - $lastActivity) > $idleTimeout) {
            self::destroySession();
            self::unauthorized('sessao_expirada_inatividade');
        }
        if (($now - $startedAt) > $absoluteTimeout) {
            self::destroySession();
            self::unauthorized('sessao_expirada_limite');
        }

        $_SESSION['_auth_started_at'] = $startedAt;
        if ($refreshActivity) {
            $_SESSION['_last_activity'] = $now;
        }
    }

    public static function touchActivity(): void
    {
        if (self::isLoggedIn()) {
            $_SESSION['_last_activity'] = time();
        }
    }

    public static function sessionStatus(): array
    {
        self::assertSessionValid(false);
        $now = time();
        $startedAt = (int)($_SESSION['_auth_started_at'] ?? $_SESSION['auth_at'] ?? $now);
        $lastActivity = (int)($_SESSION['_last_activity'] ?? $startedAt);
        $idleTimeout = defined('SESSION_IDLE_TIMEOUT') ? (int)SESSION_IDLE_TIMEOUT : 3600;
        $absoluteTimeout = defined('SESSION_ABSOLUTE_TIMEOUT') ? (int)SESSION_ABSOLUTE_TIMEOUT : 43200;

        return [
            'ok' => true,
            'autenticado' => true,
            'idle_expira_em' => max(0, $idleTimeout - ($now - $lastActivity)),
            'absolute_expira_em' => max(0, $absoluteTimeout - ($now - $startedAt)),
            'server_time' => $now,
        ];
    }

    public static function initializeAuthenticatedSession(array $user): void
    {
        session_regenerate_id(true);
        $now = time();
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_tipo'] = (string)$user['tipo'];
        $_SESSION['_auth_started_at'] = $now;
        $_SESSION['_last_activity'] = $now;
        $_SESSION['auth_at'] = $now; // compatibilidade
        $_SESSION['last_regen'] = $now;

        // §GPS-CONFIRM-01 (achado real: pedido 1539 não alertava um guincho
        // Online na área porque lat_atual/lng_atual ficava congelado por dias
        // — o dashboard só reenvia GPS durante atendimento ativo). A cada
        // NOVO login, apaga a confirmação de localização da sessão anterior,
        // forçando o guincho a confirmar de novo antes de ficar disponível
        // pra ofertas. Inofensivo para os demais perfis (cliente/admin/etc.),
        // já que só é lido dentro de GuinchoController.
        unset($_SESSION['guincho_localizacao_confirmada_em']);
    }

    public static function login(string $email, string $senha): array
    {
        // §IP-CANONICO-01: X-Forwarded-For sozinho é forjável pelo cliente —
        // ver RequestIpResolver.
        $ip = RequestIpResolver::resolve();
        if (!self::verificarRateLimit($ip, 'login')) {
            return ['success' => false, 'message' => 'Muitas tentativas. Tente em 5 minutos.'];
        }
        try {
            $stmt = getPDO()->prepare(
                'SELECT id, nome, email, senha_hash, tipo, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1'
            );
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                self::registrarTentativa($ip, 'login');
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
            getPDO()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$usuario['id']]);
            self::initializeAuthenticatedSession([
                'id' => (int)$usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo' => $usuario['tipo'],
                'ativo' => (bool)$usuario['ativo'],
            ]);
            return ['success' => true, 'message' => 'Login realizado com sucesso.'];
        } catch (PDOException $e) {
            error_log('[AuthService][login][database] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do servidor.'];
        }
    }

    public static function logout(): void
    {
        self::destroySession();
    }

    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $p['path'] ?: '/',
                'domain' => $p['domain'] ?? '',
                'secure' => (bool)($p['secure'] ?? false),
                'httponly' => (bool)($p['httponly'] ?? true),
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    private static function unauthorized(string $reason): void
    {
        if (self::isJsonRequest()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }
            http_response_code(401);
            echo json_encode([
                'ok' => false,
                'erro' => 'sessao_expirada',
                'motivo' => $reason,
                'mensagem' => 'Sua sessão expirou. Entre novamente para continuar.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $request = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $return = self::sanitizeReturnPath($request);
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login?motivo=' . rawurlencode($reason) . '&retorno=' . rawurlencode($return));
        exit;
    }

    private static function forbidden(): void
    {
        if (self::isJsonRequest()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'acesso_negado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(403);
        echo '<h1>403 — Acesso negado</h1>';
        exit;
    }

    public static function sanitizeReturnPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || strpos($path, '//') === 0) return '/';
        $parts = parse_url($path);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) return '/';
        return $path;
    }

    public static function verificarRateLimit(string $ip, string $rota): bool
    {
        try {
            $stmt = getPDO()->prepare('SELECT tentativas, bloqueado_ate FROM rate_limit WHERE ip = ? AND rota = ? LIMIT 1');
            $stmt->execute([$ip, $rota]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                if (!empty($r['bloqueado_ate']) && strtotime($r['bloqueado_ate']) > time()) return false;
                if ((int)$r['tentativas'] >= 5) {
                    getPDO()->prepare('UPDATE rate_limit SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE ip = ? AND rota = ?')->execute([$ip, $rota]);
                    return false;
                }
            }
        } catch (PDOException $e) {
            error_log('[AuthService][verificarRateLimit][database] ' . $e->getMessage());
            return true;
        }
        return true;
    }

    private static function registrarTentativa(string $ip, string $rota): void
    {
        try {
            getPDO()->prepare('INSERT INTO rate_limit (ip, rota, tentativas) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE tentativas = tentativas + 1')->execute([$ip, $rota]);
        } catch (PDOException $e) {
            error_log('[AuthService][registrarTentativa][database] ' . $e->getMessage());
        }
    }

    public static function gerarCsrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf_token'];
    }

    public static function validarCsrfToken(string $token): bool
    {
        return !empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
    }
}
