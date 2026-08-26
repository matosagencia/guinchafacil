<?php
// File: guinchafacil/src/Controllers/BaseController.php

/**
 * BaseController
 * - Fornece render(), redirect(), flash messages, CSRF básico, auth helpers.
 * - Mantém o contrato esperado pelos controllers existentes (AuthController/ClienteController).
 */
class BaseController
{
    /** @var PDO */
    protected $db;

    /** @var string */
    protected $basePath;

    public function __construct()
    {
        // Garante getPDO() disponível
        $dbFile = dirname(__DIR__) . '/Database.php';
        if (file_exists($dbFile)) {
            require_once $dbFile;
        }

        $this->db = function_exists('getPDO') ? getPDO() : null;

        // Detecta basePath ('' em public_html raiz, '/subpasta' se em subpasta)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        $this->basePath = $dir;

        // Compatibilidade: se index.php definiu basePath fixo e você quiser forçar,
        // basta definir a constante BASE_PATH no index.php ou config.php.
        if (defined('BASE_PATH')) {
            $this->basePath = BASE_PATH;
        }
    }

    /**
     * Renderiza uma view em /src/Views/{view}.php
     * $view exemplo: 'auth/login'
     */
    protected function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';

        // Variáveis para a view
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        // Flash message disponível na view
        $flash = $this->getFlashMessage();

        // Layout simples
        $header = dirname(__DIR__) . '/Views/layouts/header.php';
        $footer = dirname(__DIR__) . '/Views/layouts/footer.php';

        if (file_exists($header)) {
            include $header;
        }

        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            http_response_code(500);
            echo '<h1>500 — View não encontrada</h1>';
            echo '<p>' . htmlspecialchars($viewFile) . '</p>';
        }

        if (file_exists($footer)) {
            include $footer;
        }
    }

    /**
     * Redireciona para rota interna (ex.: /login)
     */
    protected function redirect(string $path): void
    {
        // Se vier URL absoluta, respeita
        if (preg_match('#^https?://#i', $path)) {
            header('Location: ' . $path);
            exit;
        }

        // Garante barra inicial
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        $location = ($this->basePath ?: '') . $path;
        header('Location: ' . $location);
        exit;
    }

    /**
     * Flash message simples via sessão.
     */
    protected function setFlashMessage(string $message, string $type = 'info'): void
    {
        $_SESSION['_flash'] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    protected function getFlashMessage(): ?array
    {
        if (!isset($_SESSION['_flash'])) {
            return null;
        }
        $msg = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $msg;
    }

    /**
     * CSRF básico por sessão.
     */
    protected function generateCSRFToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    protected function validateCSRFToken(string $token): bool
    {
        if (empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['_csrf_token'], (string)$token);
    }

    /**
     * Auth helpers (usa AuthService existente).
     */
    protected function isAuthenticated(): bool
    {
        if (class_exists('AuthService') && method_exists('AuthService', 'isLoggedIn')) {
            return (bool) AuthService::isLoggedIn();
        }
        return !empty($_SESSION['user']);
    }

    protected function getCurrentUser(): ?array
    {
        if (class_exists('AuthService') && method_exists('AuthService', 'getCurrentUser')) {
            return AuthService::getCurrentUser();
        }
        return $_SESSION['user'] ?? null;
    }
}
